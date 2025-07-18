#!/usr/bin/env python3
"""
PyTorch CAPTCHA Recognition Model Training
Trains a CNN model to recognize 5-character alphanumeric CAPTCHAs
"""

import os
import torch
import torch.nn as nn
import torch.optim as optim
from torch.utils.data import Dataset, DataLoader
from torchvision import transforms
from PIL import Image
import numpy as np
from sklearn.model_selection import train_test_split
import matplotlib.pyplot as plt
from tqdm import tqdm
import string
import warnings

# Suppress warnings
warnings.filterwarnings('ignore')

# Configuration
print(f"PyTorch version: {torch.__version__}")
print(f"CUDA available: {torch.cuda.is_available()}")

# Use CUDA if available
if torch.cuda.is_available():
    DEVICE = torch.device('cuda')
    print(f"CUDA version: {torch.version.cuda}")
    print(f"GPU: {torch.cuda.get_device_name(0)}")
    # Enable CUDA debugging
    torch.backends.cudnn.benchmark = False
    torch.backends.cudnn.deterministic = True
    # Disable CUDA caching to get better error messages
    import os
    os.environ['CUDA_LAUNCH_BLOCKING'] = '1'
else:
    DEVICE = torch.device('cpu')
print(f"Using device: {DEVICE}")

BATCH_SIZE = 32
LEARNING_RATE = 0.001
NUM_EPOCHS = 50
IMAGE_HEIGHT = 60
IMAGE_WIDTH = 200
SEQUENCE_LENGTH = 5

# Character set - excluding '0' since it's not in the dataset
# Based on dataset analysis, '0' is never used in CAPTCHAs
CHARSET = '123456789' + string.ascii_uppercase + string.ascii_lowercase  # 1-9, A-Z, a-z
NUM_CLASSES = len(CHARSET)  # Should be 61
CHAR_TO_IDX = {char: idx for idx, char in enumerate(CHARSET)}
IDX_TO_CHAR = {idx: char for char, idx in CHAR_TO_IDX.items()}

print(f"Character set ({len(CHARSET)} chars): {CHARSET[:20]}...{CHARSET[-20:]}")
print(f"NUM_CLASSES: {NUM_CLASSES}, CHARSET length: {len(CHARSET)}")
assert NUM_CLASSES == len(CHARSET), f"NUM_CLASSES ({NUM_CLASSES}) must equal CHARSET length ({len(CHARSET)})"

class CaptchaDataset(Dataset):
    """Dataset for loading CAPTCHA images"""
    
    def __init__(self, image_paths, labels, transform=None):
        self.image_paths = image_paths
        self.labels = labels
        self.transform = transform
        
        # Validate all labels
        self.valid_indices = []
        for idx, (path, label) in enumerate(zip(image_paths, labels)):
            if len(label) == SEQUENCE_LENGTH and all(c in CHARSET for c in label):
                self.valid_indices.append(idx)
            else:
                print(f"Skipping invalid label: '{label}' from {path}")
        
        print(f"Valid samples: {len(self.valid_indices)} out of {len(image_paths)}")
    
    def __len__(self):
        return len(self.valid_indices)
    
    def __getitem__(self, idx):
        real_idx = self.valid_indices[idx]
        img_path = self.image_paths[real_idx]
        label = self.labels[real_idx]
        
        try:
            # Load and transform image
            image = Image.open(img_path).convert('RGB')
            if self.transform:
                image = self.transform(image)
            
            # Convert label string to tensor of indices
            label_tensor = torch.zeros(SEQUENCE_LENGTH, dtype=torch.long)
            for i, char in enumerate(label):
                char_idx = CHAR_TO_IDX.get(char, -1)
                if char_idx == -1:
                    print(f"ERROR: Character '{char}' (ord={ord(char)}) not in CHARSET")
                    print(f"Label: '{label}' from {img_path}")
                    print(f"Available chars: {CHARSET}")
                    raise ValueError(f"Invalid character '{char}' in label")
                if not (0 <= char_idx < NUM_CLASSES):
                    print(f"ERROR: char_idx {char_idx} out of bounds [0, {NUM_CLASSES})")
                    raise ValueError(f"Character index out of bounds")
                label_tensor[i] = char_idx
            
            return image, label_tensor
            
        except Exception as e:
            print(f"Error processing {img_path}: {e}")
            # Return first valid sample instead
            if len(self.valid_indices) > 0:
                return self.__getitem__(0)
            else:
                raise

class CaptchaCNN(nn.Module):
    """CNN model for CAPTCHA recognition"""
    
    def __init__(self):
        super(CaptchaCNN, self).__init__()
        
        # Convolutional layers
        self.conv1 = nn.Conv2d(3, 32, kernel_size=3, padding=1)
        self.conv2 = nn.Conv2d(32, 64, kernel_size=3, padding=1)
        self.conv3 = nn.Conv2d(64, 128, kernel_size=3, padding=1)
        self.pool = nn.MaxPool2d(2, 2)
        self.dropout = nn.Dropout(0.25)
        
        # Calculate size after convolutions
        conv_output_height = IMAGE_HEIGHT // 8  # 3 pooling layers
        conv_output_width = IMAGE_WIDTH // 8
        linear_input_size = 128 * conv_output_height * conv_output_width
        
        # Fully connected layers
        self.fc1 = nn.Linear(linear_input_size, 512)
        self.fc2 = nn.Linear(512, 256)
        
        # Output layers for each character position
        self.char_outputs = nn.ModuleList([
            nn.Linear(256, NUM_CLASSES) for _ in range(SEQUENCE_LENGTH)
        ])
        
    def forward(self, x):
        # Convolutional layers
        x = self.pool(torch.relu(self.conv1(x)))
        x = self.pool(torch.relu(self.conv2(x)))
        x = self.pool(torch.relu(self.conv3(x)))
        x = self.dropout(x)
        
        # Flatten
        x = x.view(x.size(0), -1)
        
        # Fully connected layers
        x = torch.relu(self.fc1(x))
        x = self.dropout(x)
        x = torch.relu(self.fc2(x))
        
        # Output for each character
        outputs = []
        for char_output in self.char_outputs:
            outputs.append(char_output(x))
        
        return outputs

def load_dataset(base_dir):
    """Load dataset from base directory"""
    image_paths = []
    labels = []
    
    if not os.path.exists(base_dir):
        raise ValueError(f"Base directory {base_dir} does not exist")
    
    files = [f for f in os.listdir(base_dir) if f.endswith('.png')]
    print(f"Found {len(files)} PNG files in {base_dir}")
    
    for filename in files:
        label = filename[:-4]  # Remove .png extension
        # Only include valid labels
        if len(label) == SEQUENCE_LENGTH:
            image_paths.append(os.path.join(base_dir, filename))
            labels.append(label)
        else:
            print(f"Skipping file with invalid label length: {filename}")
    
    print(f"Loaded {len(image_paths)} valid samples")
    return image_paths, labels

def train_model(model, train_loader, val_loader, num_epochs):
    """Train the model"""
    criterion = nn.CrossEntropyLoss()
    optimizer = optim.Adam(model.parameters(), lr=LEARNING_RATE)
    scheduler = optim.lr_scheduler.ReduceLROnPlateau(optimizer, patience=5, factor=0.5)
    
    train_losses = []
    val_accuracies = []
    best_val_acc = 0
    
    for epoch in range(num_epochs):
        # Training phase
        model.train()
        train_loss = 0
        batch_count = 0
        
        for batch_idx, (images, labels) in enumerate(tqdm(train_loader, desc=f'Epoch {epoch+1}/{num_epochs}')):
            try:
                images = images.to(DEVICE)
                labels = labels.to(DEVICE)
                
                # Verify label indices are valid
                assert labels.max() < NUM_CLASSES, f"Label index {labels.max()} >= NUM_CLASSES {NUM_CLASSES}"
                assert labels.min() >= 0, f"Label index {labels.min()} < 0"
                
                optimizer.zero_grad()
                outputs = model(images)
                
                # Calculate loss for each character position
                loss = 0
                for i in range(SEQUENCE_LENGTH):
                    char_loss = criterion(outputs[i], labels[:, i])
                    loss += char_loss
                
                loss.backward()
                optimizer.step()
                train_loss += loss.item()
                batch_count += 1
                
            except Exception as e:
                print(f"\nError in batch {batch_idx}: {e}")
                print(f"Images shape: {images.shape}")
                print(f"Labels shape: {labels.shape}")
                print(f"Labels: {labels}")
                if DEVICE.type == 'cuda':
                    print("CUDA error detected. Consider running with CPU by setting DEVICE = torch.device('cpu')")
                raise
        
        avg_train_loss = train_loss / max(batch_count, 1)
        train_losses.append(avg_train_loss)
        
        # Validation phase
        model.eval()
        correct_chars = 0
        correct_sequences = 0
        total_chars = 0
        total_sequences = 0
        
        with torch.no_grad():
            for images, labels in val_loader:
                images = images.to(DEVICE)
                labels = labels.to(DEVICE)
                
                outputs = model(images)
                
                # Get predictions
                predictions = []
                for i in range(SEQUENCE_LENGTH):
                    _, predicted = torch.max(outputs[i], 1)
                    predictions.append(predicted)
                
                predictions = torch.stack(predictions, dim=1)
                
                # Calculate accuracy
                correct_chars += (predictions == labels).sum().item()
                total_chars += labels.numel()
                
                # Check if entire sequence is correct
                correct_sequences += (predictions == labels).all(dim=1).sum().item()
                total_sequences += labels.size(0)
        
        char_accuracy = correct_chars / total_chars if total_chars > 0 else 0
        seq_accuracy = correct_sequences / total_sequences if total_sequences > 0 else 0
        val_accuracies.append(seq_accuracy)
        
        print(f'Epoch [{epoch+1}/{num_epochs}], Loss: {avg_train_loss:.4f}, '
              f'Char Acc: {char_accuracy:.4f}, Seq Acc: {seq_accuracy:.4f}')
        
        # Learning rate scheduling
        scheduler.step(seq_accuracy)
        
        # Save best model
        if seq_accuracy > best_val_acc:
            best_val_acc = seq_accuracy
            torch.save({
                'epoch': epoch,
                'model_state_dict': model.state_dict(),
                'optimizer_state_dict': optimizer.state_dict(),
                'char_accuracy': char_accuracy,
                'seq_accuracy': seq_accuracy,
                'num_classes': NUM_CLASSES,
                'charset': CHARSET,
            }, 'captcha_model_best.pth')
            print(f"Saved best model with accuracy: {seq_accuracy:.4f}")
    
    return train_losses, val_accuracies

def main():
    # Data augmentation and normalization
    transform = transforms.Compose([
        transforms.Resize((IMAGE_HEIGHT, IMAGE_WIDTH)),
        transforms.ToTensor(),
        transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225])
    ])
    
    # Load dataset
    print("Loading dataset...")
    base_dir = './base'
    
    try:
        image_paths, labels = load_dataset(base_dir)
    except Exception as e:
        print(f"Error loading dataset: {e}")
        return
    
    if len(image_paths) == 0:
        print("No valid images found!")
        return
    
    # Split dataset
    train_paths, val_paths, train_labels, val_labels = train_test_split(
        image_paths, labels, test_size=0.2, random_state=42
    )
    
    print(f"Training samples: {len(train_paths)}")
    print(f"Validation samples: {len(val_paths)}")
    
    # Create datasets and dataloaders
    train_dataset = CaptchaDataset(train_paths, train_labels, transform)
    val_dataset = CaptchaDataset(val_paths, val_labels, transform)
    
    if len(train_dataset) == 0 or len(val_dataset) == 0:
        print("No valid samples after filtering!")
        return
    
    # Use single worker to avoid multiprocessing issues
    train_loader = DataLoader(train_dataset, batch_size=BATCH_SIZE, shuffle=True, num_workers=0)
    val_loader = DataLoader(val_dataset, batch_size=BATCH_SIZE, shuffle=False, num_workers=0)
    
    # Create model
    print(f"Creating model on {DEVICE}...")
    model = CaptchaCNN().to(DEVICE)
    
    # Count parameters
    total_params = sum(p.numel() for p in model.parameters())
    trainable_params = sum(p.numel() for p in model.parameters() if p.requires_grad)
    print(f"Total parameters: {total_params:,}")
    print(f"Trainable parameters: {trainable_params:,}")
    
    # Train model
    print("Training model...")
    try:
        train_losses, val_accuracies = train_model(model, train_loader, val_loader, NUM_EPOCHS)
        
        # Plot training history
        plt.figure(figsize=(12, 4))
        
        plt.subplot(1, 2, 1)
        plt.plot(train_losses)
        plt.title('Training Loss')
        plt.xlabel('Epoch')
        plt.ylabel('Loss')
        
        plt.subplot(1, 2, 2)
        plt.plot(val_accuracies)
        plt.title('Validation Accuracy')
        plt.xlabel('Epoch')
        plt.ylabel('Sequence Accuracy')
        
        plt.savefig('training_history.png')
        print("Training complete! Model saved as 'captcha_model_best.pth'")
        print("Training history saved as 'training_history.png'")
        
    except Exception as e:
        print(f"Training failed: {e}")
        if DEVICE.type == 'cuda':
            print("\nTry running with CPU by changing line 25 to: DEVICE = torch.device('cpu')")

if __name__ == '__main__':
    main()
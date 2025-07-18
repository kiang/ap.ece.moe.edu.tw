#!/usr/bin/env python3
"""
Simplified PyTorch CAPTCHA training script with better error handling
"""
import os
import torch
import torch.nn as nn
import torch.optim as optim
from torch.utils.data import Dataset, DataLoader
from torchvision import transforms
from PIL import Image
import string
from tqdm import tqdm

# Enable CUDA debugging
if torch.cuda.is_available():
    os.environ['CUDA_LAUNCH_BLOCKING'] = '1'

# Configuration
DEVICE = torch.device('cuda' if torch.cuda.is_available() else 'cpu')
BATCH_SIZE = 16  # Smaller batch size for stability
EPOCHS = 10  # Fewer epochs for testing
LR = 0.001

# Image settings
IMG_H, IMG_W = 60, 200
SEQ_LEN = 5

# Character set (no '0' based on dataset analysis)
CHARSET = '123456789' + string.ascii_uppercase + string.ascii_lowercase
NUM_CLASSES = len(CHARSET)
CHAR_TO_IDX = {c: i for i, c in enumerate(CHARSET)}

print(f"Device: {DEVICE}")
print(f"Charset ({NUM_CLASSES} chars): {CHARSET}")

class SimpleDataset(Dataset):
    def __init__(self, img_dir, transform=None):
        self.transform = transform
        self.samples = []
        
        # Load valid samples only
        for fname in os.listdir(img_dir):
            if fname.endswith('.png'):
                label = fname[:-4]
                if len(label) == SEQ_LEN and all(c in CHARSET for c in label):
                    self.samples.append((os.path.join(img_dir, fname), label))
        
        print(f"Loaded {len(self.samples)} valid samples")
    
    def __len__(self):
        return len(self.samples)
    
    def __getitem__(self, idx):
        img_path, label = self.samples[idx]
        
        # Load image
        img = Image.open(img_path).convert('RGB')
        if self.transform:
            img = self.transform(img)
        
        # Convert label to indices
        label_idx = torch.zeros(SEQ_LEN, dtype=torch.long)
        for i, c in enumerate(label):
            label_idx[i] = CHAR_TO_IDX[c]
        
        return img, label_idx

class SimpleCNN(nn.Module):
    def __init__(self):
        super().__init__()
        # Simple CNN
        self.conv = nn.Sequential(
            nn.Conv2d(3, 32, 3, padding=1),
            nn.ReLU(),
            nn.MaxPool2d(2),
            nn.Conv2d(32, 64, 3, padding=1),
            nn.ReLU(),
            nn.MaxPool2d(2),
            nn.Conv2d(64, 128, 3, padding=1),
            nn.ReLU(),
            nn.MaxPool2d(2),
        )
        
        # Calculate feature size
        h, w = IMG_H // 8, IMG_W // 8
        fc_in = 128 * h * w
        
        # FC layers
        self.fc = nn.Sequential(
            nn.Linear(fc_in, 256),
            nn.ReLU(),
            nn.Dropout(0.5),
        )
        
        # Output heads for each character
        self.heads = nn.ModuleList([nn.Linear(256, NUM_CLASSES) for _ in range(SEQ_LEN)])
    
    def forward(self, x):
        x = self.conv(x)
        x = x.view(x.size(0), -1)
        x = self.fc(x)
        return [head(x) for head in self.heads]

def train():
    # Data preparation
    transform = transforms.Compose([
        transforms.Resize((IMG_H, IMG_W)),
        transforms.ToTensor(),
    ])
    
    dataset = SimpleDataset('./base', transform)
    if len(dataset) == 0:
        print("No valid samples found!")
        return
    
    # Split 80/20
    train_size = int(0.8 * len(dataset))
    val_size = len(dataset) - train_size
    train_data, val_data = torch.utils.data.random_split(dataset, [train_size, val_size])
    
    train_loader = DataLoader(train_data, batch_size=BATCH_SIZE, shuffle=True)
    val_loader = DataLoader(val_data, batch_size=BATCH_SIZE)
    
    # Model setup
    model = SimpleCNN().to(DEVICE)
    criterion = nn.CrossEntropyLoss()
    optimizer = optim.Adam(model.parameters(), lr=LR)
    
    print(f"Training on {train_size} samples, validating on {val_size}")
    
    # Training loop
    for epoch in range(EPOCHS):
        # Train
        model.train()
        train_loss = 0
        for imgs, labels in tqdm(train_loader, desc=f'Epoch {epoch+1}/{EPOCHS}'):
            imgs, labels = imgs.to(DEVICE), labels.to(DEVICE)
            
            optimizer.zero_grad()
            outputs = model(imgs)
            
            # Loss for each position
            loss = sum(criterion(outputs[i], labels[:, i]) for i in range(SEQ_LEN))
            
            loss.backward()
            optimizer.step()
            train_loss += loss.item()
        
        # Validate
        model.eval()
        correct = 0
        total = 0
        with torch.no_grad():
            for imgs, labels in val_loader:
                imgs, labels = imgs.to(DEVICE), labels.to(DEVICE)
                outputs = model(imgs)
                
                # Get predictions
                preds = torch.stack([out.argmax(1) for out in outputs], dim=1)
                
                # Count correct sequences
                correct += (preds == labels).all(dim=1).sum().item()
                total += labels.size(0)
        
        acc = correct / total
        print(f'Epoch {epoch+1}: Loss={train_loss/len(train_loader):.4f}, Acc={acc:.4f}')
        
        # Save if good
        if acc > 0.5:
            torch.save({
                'model': model.state_dict(),
                'charset': CHARSET,
                'num_classes': NUM_CLASSES,
            }, f'model_simple_{acc:.3f}.pth')
            print(f'Saved model with {acc:.3f} accuracy')

if __name__ == '__main__':
    train()
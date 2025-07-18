#!/usr/bin/env python3
"""
CAPTCHA Prediction Script
Uses trained PyTorch model to predict CAPTCHA text from images
"""

import sys
import torch
import torch.nn as nn
from torchvision import transforms
from PIL import Image
import string
import json
import os

# Configuration
DEVICE = torch.device('cuda' if torch.cuda.is_available() else 'cpu')
IMAGE_HEIGHT = 60
IMAGE_WIDTH = 200
SEQUENCE_LENGTH = 5

# Character set - must match training script exactly (no '0')
CHARSET = '123456789' + string.ascii_uppercase + string.ascii_lowercase  # 1-9, A-Z, a-z
NUM_CLASSES = len(CHARSET)  # Should be 61
IDX_TO_CHAR = {idx: char for idx, char in enumerate(CHARSET)}

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
        conv_output_height = IMAGE_HEIGHT // 8
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

def load_model(model_path='captcha_model_best.pth'):
    """Load trained model"""
    model = CaptchaCNN().to(DEVICE)
    
    if os.path.exists(model_path):
        checkpoint = torch.load(model_path, map_location=DEVICE)
        model.load_state_dict(checkpoint['model_state_dict'])
        model.eval()
        
        # Verify model was trained with correct number of classes
        if 'num_classes' in checkpoint and checkpoint['num_classes'] != NUM_CLASSES:
            print(f"Warning: Model trained with {checkpoint['num_classes']} classes, but script expects {NUM_CLASSES}")
        
        return model
    else:
        raise FileNotFoundError(f"Model file {model_path} not found")

def predict_image(model, image_path):
    """Predict CAPTCHA text from image"""
    # Load and preprocess image
    transform = transforms.Compose([
        transforms.Resize((IMAGE_HEIGHT, IMAGE_WIDTH)),
        transforms.ToTensor(),
        transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225])
    ])
    
    image = Image.open(image_path).convert('RGB')
    image_tensor = transform(image).unsqueeze(0).to(DEVICE)
    
    # Make prediction
    with torch.no_grad():
        outputs = model(image_tensor)
        
        # Get predicted characters
        predicted_text = ""
        confidences = []
        
        for i in range(SEQUENCE_LENGTH):
            probs = torch.softmax(outputs[i], dim=1)
            confidence, predicted = torch.max(probs, 1)
            char_idx = predicted.item()
            
            if char_idx < len(IDX_TO_CHAR):
                predicted_text += IDX_TO_CHAR[char_idx]
                confidences.append(confidence.item())
            else:
                predicted_text += "?"
                confidences.append(0.0)
    
    avg_confidence = sum(confidences) / len(confidences) if confidences else 0
    
    return {
        'text': predicted_text,
        'confidence': avg_confidence,
        'char_confidences': confidences
    }

def main():
    if len(sys.argv) < 2:
        print("Usage: python predict_captcha.py <image_path> [model_path]")
        sys.exit(1)
    
    image_path = sys.argv[1]
    model_path = sys.argv[2] if len(sys.argv) > 2 else 'captcha_model_best.pth'
    
    try:
        # Load model
        model = load_model(model_path)
        
        # Make prediction
        result = predict_image(model, image_path)
        
        # Output result as JSON for easy parsing
        print(json.dumps(result))
        
    except Exception as e:
        error_result = {
            'error': str(e),
            'text': '',
            'confidence': 0
        }
        print(json.dumps(error_result))
        sys.exit(1)

if __name__ == '__main__':
    main()
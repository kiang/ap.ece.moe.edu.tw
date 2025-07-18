#!/usr/bin/env python3
"""
Safe CAPTCHA prediction script with better error handling
"""
import sys
import os
import json

def main():
    if len(sys.argv) < 2:
        result = {'error': 'Usage: python predict_safe.py <image_path> [model_path]', 'text': '', 'confidence': 0}
        print(json.dumps(result))
        sys.exit(1)
    
    image_path = sys.argv[1]
    model_path = sys.argv[2] if len(sys.argv) > 2 else 'captcha_model_best.pth'
    
    # Check if files exist
    if not os.path.exists(image_path):
        result = {'error': f'Image file not found: {image_path}', 'text': '', 'confidence': 0}
        print(json.dumps(result))
        sys.exit(1)
    
    if not os.path.exists(model_path):
        result = {'error': f'Model file not found: {model_path}', 'text': '', 'confidence': 0}
        print(json.dumps(result))
        sys.exit(1)
    
    try:
        import torch
        import torch.nn as nn
        from torchvision import transforms
        from PIL import Image
        import string
    except ImportError as e:
        result = {'error': f'Missing Python module: {e}', 'text': '', 'confidence': 0}
        print(json.dumps(result))
        sys.exit(1)
    
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
    
    try:
        # Load model
        model = CaptchaCNN().to(DEVICE)
        checkpoint = torch.load(model_path, map_location=DEVICE)
        model.load_state_dict(checkpoint['model_state_dict'])
        model.eval()
        
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
        
        result = {
            'text': predicted_text,
            'confidence': avg_confidence,
            'char_confidences': confidences
        }
        
    except Exception as e:
        result = {
            'error': str(e),
            'text': '',
            'confidence': 0
        }
    
    print(json.dumps(result))

if __name__ == '__main__':
    main()
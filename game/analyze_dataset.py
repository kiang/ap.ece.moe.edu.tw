#!/usr/bin/env python3
"""
Analyze character distribution in CAPTCHA dataset
"""
import os
from collections import Counter
import string

def analyze_captchas(base_dir='./base'):
    """Analyze character distribution in dataset"""
    all_chars = Counter()
    label_lengths = Counter()
    total_files = 0
    valid_files = 0
    
    print(f"Analyzing images in {base_dir}...")
    
    for filename in os.listdir(base_dir):
        if filename.endswith('.png'):
            total_files += 1
            label = filename[:-4]  # Remove .png
            label_lengths[len(label)] += 1
            
            if len(label) == 5:
                valid_files += 1
                for char in label:
                    all_chars[char] += 1
    
    print(f"\nTotal files: {total_files}")
    print(f"Valid files (5 chars): {valid_files}")
    
    print(f"\nLabel length distribution:")
    for length, count in sorted(label_lengths.items()):
        print(f"  {length} chars: {count} files")
    
    print(f"\nUnique characters found: {len(all_chars)}")
    print(f"Total characters: {sum(all_chars.values())}")
    
    # Expected character set
    expected = set(string.digits + string.ascii_uppercase + string.ascii_lowercase)
    found = set(all_chars.keys())
    
    print(f"\nCharacter frequency (top 20):")
    for char, count in all_chars.most_common(20):
        print(f"  '{char}' (ord={ord(char)}): {count}")
    
    # Check for unexpected characters
    unexpected = found - expected
    if unexpected:
        print(f"\nWARNING: Unexpected characters found:")
        for char in sorted(unexpected):
            print(f"  '{char}' (ord={ord(char)}): {all_chars[char]} times")
    
    # Check for missing expected characters
    missing = expected - found
    if missing:
        print(f"\nMissing expected characters:")
        for char in sorted(missing):
            print(f"  '{char}'")
    
    # Character type distribution
    digits = sum(1 for c in found if c in string.digits)
    upper = sum(1 for c in found if c in string.ascii_uppercase)
    lower = sum(1 for c in found if c in string.ascii_lowercase)
    other = len(found) - digits - upper - lower
    
    print(f"\nCharacter types:")
    print(f"  Digits (0-9): {digits}")
    print(f"  Uppercase (A-Z): {upper}")
    print(f"  Lowercase (a-z): {lower}")
    print(f"  Other: {other}")
    
    return all_chars, label_lengths

if __name__ == '__main__':
    analyze_captchas()
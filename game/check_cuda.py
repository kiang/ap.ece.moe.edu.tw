#!/usr/bin/env python3
"""
Check CUDA environment and test basic operations
"""
import torch
import torch.nn as nn

print("=== CUDA Environment Check ===")
print(f"PyTorch version: {torch.__version__}")
print(f"CUDA available: {torch.cuda.is_available()}")

if torch.cuda.is_available():
    print(f"CUDA version: {torch.version.cuda}")
    print(f"CUDNN version: {torch.backends.cudnn.version()}")
    print(f"Number of GPUs: {torch.cuda.device_count()}")
    
    for i in range(torch.cuda.device_count()):
        print(f"\nGPU {i}: {torch.cuda.get_device_name(i)}")
        print(f"  Memory: {torch.cuda.get_device_properties(i).total_memory / 1024**3:.2f} GB")
        print(f"  Compute Capability: {torch.cuda.get_device_properties(i).major}.{torch.cuda.get_device_properties(i).minor}")
    
    # Test basic CUDA operations
    print("\n=== Testing Basic CUDA Operations ===")
    try:
        # Test tensor creation
        x = torch.randn(10, 10).cuda()
        print("✓ Tensor creation successful")
        
        # Test matrix multiplication
        y = torch.mm(x, x)
        print("✓ Matrix multiplication successful")
        
        # Test cross entropy loss with correct indices
        criterion = nn.CrossEntropyLoss().cuda()
        outputs = torch.randn(32, 62).cuda()  # 62 classes
        targets = torch.randint(0, 62, (32,)).cuda()  # Valid indices 0-61
        loss = criterion(outputs, targets)
        print("✓ Cross entropy loss successful")
        
        # Test with invalid indices (this should fail)
        print("\nTesting with invalid indices (should fail):")
        try:
            bad_targets = torch.tensor([0, 61, 62, 100]).cuda()  # 62 and 100 are invalid
            loss = criterion(outputs[:4], bad_targets)
            print("✗ Should have failed with invalid indices!")
        except Exception as e:
            print(f"✓ Correctly caught error: {type(e).__name__}")
        
        print("\nCUDA tests passed! It's safe to use CUDA.")
        
    except Exception as e:
        print(f"\n✗ CUDA test failed: {e}")
        print("You may need to use CPU or debug the CUDA installation")
else:
    print("\nCUDA is not available. You'll need to use CPU for training.")
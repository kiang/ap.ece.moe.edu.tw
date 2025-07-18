#!/bin/bash
# Wrapper script to run PyTorch prediction with correct environment

# Set environment variables with multiple potential paths
export PATH="/usr/local/bin:/usr/bin:/bin"

# Add multiple potential PyTorch installation paths
PYTORCH_PATHS=(
    "/home/kiang/.local/lib/python3.10/site-packages"
    "/home/kiang/.local/lib/python3.11/site-packages"
    "/usr/local/lib/python3.10/dist-packages"
    "/usr/local/lib/python3.11/dist-packages"
    "/usr/lib/python3/dist-packages"
    "/usr/lib/python3.10/dist-packages"
    "/usr/lib/python3.11/dist-packages"
)

# Build PYTHONPATH with existing paths
PYTHON_PATH_STRING=""
for path in "${PYTORCH_PATHS[@]}"; do
    if [ -d "$path" ]; then
        if [ -z "$PYTHON_PATH_STRING" ]; then
            PYTHON_PATH_STRING="$path"
        else
            PYTHON_PATH_STRING="$PYTHON_PATH_STRING:$path"
        fi
    fi
done

# Add existing PYTHONPATH if it exists
if [ ! -z "$PYTHONPATH" ]; then
    PYTHON_PATH_STRING="$PYTHON_PATH_STRING:$PYTHONPATH"
fi

export PYTHONPATH="$PYTHON_PATH_STRING"

# Change to the script directory
cd "$(dirname "$0")"

# Check if arguments are provided
if [ $# -lt 1 ]; then
    echo '{"error": "Usage: predict_wrapper.sh <image_path> [model_path]", "text": "", "confidence": 0}'
    exit 1
fi

IMAGE_PATH="$1"
MODEL_PATH="${2:-captcha_model_best.pth}"

# Check if files exist
if [ ! -f "$IMAGE_PATH" ]; then
    echo "{\"error\": \"Image file not found: $IMAGE_PATH\", \"text\": \"\", \"confidence\": 0}"
    exit 1
fi

if [ ! -f "$MODEL_PATH" ]; then
    echo "{\"error\": \"Model file not found: $MODEL_PATH\", \"text\": \"\", \"confidence\": 0}"
    exit 1
fi

# Debug mode - check if torch is available
if [ "$3" = "debug" ]; then
    echo "Debug Mode - Checking PyTorch availability:"
    echo "PYTHONPATH: $PYTHONPATH"
    echo "Python version:"
    /usr/bin/python3 --version
    echo "Trying to import torch:"
    /usr/bin/python3 -c "try:
    import torch
    print('SUCCESS: PyTorch version', torch.__version__)
except ImportError as e:
    print('ERROR:', e)
    import sys
    print('Python paths:')
    for p in sys.path: print(' -', p)"
    exit 0
fi

# Try to run the prediction script
if [ -f "predict_safe.py" ]; then
    /usr/bin/python3 predict_safe.py "$IMAGE_PATH" "$MODEL_PATH"
elif [ -f "predict_captcha.py" ]; then
    /usr/bin/python3 predict_captcha.py "$IMAGE_PATH" "$MODEL_PATH"
else
    echo '{"error": "No prediction script found", "text": "", "confidence": 0}'
    exit 1
fi
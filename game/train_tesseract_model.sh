#!/bin/bash

# Tesseract training script for custom CAPTCHA model
# This script trains a new Tesseract model optimized for the specific CAPTCHA style

set -e

# Configuration
LANG_NAME="captcha"
TRAINING_DIR="./training"
OUTPUT_DIR="./training/output"
GROUND_TRUTH_DIR="${TRAINING_DIR}/ground-truth"

# Create necessary directories
mkdir -p "${OUTPUT_DIR}"

echo "=== Tesseract Model Training Script ==="
echo "Training language: ${LANG_NAME}"
echo ""

# Step 1: Prepare training data if not already done
if [ ! -d "${GROUND_TRUTH_DIR}" ] || [ -z "$(ls -A ${GROUND_TRUTH_DIR})" ]; then
    echo "Preparing training data..."
    php prepare_training_data.php
fi

# Step 2: Generate box files for each image
echo "Generating box files..."
cd "${GROUND_TRUTH_DIR}"
for img in *.png; do
    if [ -f "$img" ]; then
        base=$(basename "$img" .png)
        if [ ! -f "${base}.box" ]; then
            # Generate box file with character positions
            tesseract "$img" "$base" batch.nochop makebox
        fi
    fi
done
cd -

# Step 3: Create font_properties file
echo "Creating font properties..."
echo "captcha 0 0 0 0 0" > "${TRAINING_DIR}/font_properties"

# Step 4: Generate training files
echo "Generating training files..."
cd "${GROUND_TRUTH_DIR}"
for img in *.png; do
    if [ -f "$img" ]; then
        base=$(basename "$img" .png)
        if [ ! -f "${base}.tr" ]; then
            tesseract "$img" "$base" box.train
        fi
    fi
done
cd -

# Step 5: Generate unicharset
echo "Generating character set..."
unicharset_extractor "${GROUND_TRUTH_DIR}"/*.box
mv unicharset "${OUTPUT_DIR}/"

# Step 6: Generate shape files
echo "Generating shape files..."
shapeclustering -F "${TRAINING_DIR}/font_properties" -U "${OUTPUT_DIR}/unicharset" "${GROUND_TRUTH_DIR}"/*.tr
mv shapetable "${OUTPUT_DIR}/"
mv shape_table "${OUTPUT_DIR}/" 2>/dev/null || true

# Step 7: Generate feature files
echo "Extracting features..."
mftraining -F "${TRAINING_DIR}/font_properties" -U "${OUTPUT_DIR}/unicharset" -O "${OUTPUT_DIR}/${LANG_NAME}.unicharset" "${GROUND_TRUTH_DIR}"/*.tr
mv inttemp "${OUTPUT_DIR}/${LANG_NAME}.inttemp"
mv pffmtable "${OUTPUT_DIR}/${LANG_NAME}.pffmtable"
mv shapetable "${OUTPUT_DIR}/${LANG_NAME}.shapetable"

# Step 8: Generate normproto
echo "Generating normalization prototypes..."
cntraining "${GROUND_TRUTH_DIR}"/*.tr
mv normproto "${OUTPUT_DIR}/${LANG_NAME}.normproto"

# Step 9: Rename files for Tesseract format
cd "${OUTPUT_DIR}"
for file in ${LANG_NAME}.*; do
    if [[ ! "$file" =~ \.traineddata$ ]]; then
        newname=$(echo "$file" | sed "s/${LANG_NAME}\./${LANG_NAME}./")
        if [ "$file" != "$newname" ]; then
            mv "$file" "$newname"
        fi
    fi
done
cd -

# Step 10: Combine into traineddata file
echo "Combining into traineddata file..."
combine_tessdata "${OUTPUT_DIR}/${LANG_NAME}."

# Step 11: Move to tessdata directory (optional - for system-wide installation)
echo ""
echo "Training complete!"
echo "Model file created: ${OUTPUT_DIR}/${LANG_NAME}.traineddata"
echo ""
echo "To use this model:"
echo "1. Copy to Tesseract data directory:"
echo "   sudo cp ${OUTPUT_DIR}/${LANG_NAME}.traineddata /usr/share/tesseract-ocr/5/tessdata/"
echo "2. Or use with --tessdata-dir parameter:"
echo "   tesseract image.png output -l ${LANG_NAME} --tessdata-dir ${OUTPUT_DIR}"
echo ""
echo "For best results with CAPTCHA, use: --psm 8 -c load_system_dawg=0 -c load_freq_dawg=0"
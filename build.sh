#!/bin/bash
set -e

# Configuration
IMAGE_NAME="ships-app"
IMAGE_TAG="${1:-latest}"
DOCKERFILE="DockerfileProd"

echo "Building Docker Image: ${IMAGE_NAME}:${IMAGE_TAG}"
echo "================================================"

# Check if Dockerfile exists
if [ ! -f "$DOCKERFILE" ]; then
    echo "Error: $DOCKERFILE not found!"
    exit 1
fi

# Check if docker folder exists
if [ ! -d "docker" ]; then
    echo "Error: docker/ folder not found!"
    exit 1
fi

# Clean old build artifacts to ensure fresh build
echo "Cleaning old build artifacts..."
rm -rf public/build/* || true

# Build the image with no cache to ensure fresh build
echo "Building image (no cache for fresh build)..."
docker build \
    --no-cache \
    -f "$DOCKERFILE" \
    -t "${IMAGE_NAME}:${IMAGE_TAG}" \
    --progress=plain \
    .

# Check if build was successful
if [ $? -eq 0 ]; then
    echo ""
    echo "Build successful!"
    echo "================================================"
    echo "Image Info:"
    docker images "${IMAGE_NAME}:${IMAGE_TAG}" --format "table {{.Repository}}\t{{.Tag}}\t{{.Size}}\t{{.CreatedAt}}"
    echo ""
    echo "Next steps:"
    echo "  Test locally:    ./deploy.sh dev"
    echo "  Deploy to prod:  ./deploy.sh prod"
else
    echo ""
    echo "Build failed!"
    exit 1
fi
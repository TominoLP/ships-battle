#!/bin/bash
set -e

# Configuration
IMAGE_NAME="ships-app"
IMAGE_TAG="${1:-latest}"
DOCKERFILE="DockerfileProd"
USE_CACHE="${USE_CACHE:-true}"  # Set to "false" to force no-cache build

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

# Determine cache strategy
CACHE_ARGS=()
if [ "$USE_CACHE" = "false" ]; then
    echo "Building with NO CACHE (forced fresh build)..."
    CACHE_ARGS+=(--no-cache)
else
    echo "Building with cache enabled..."
    # Use BuildKit cache mounts for better performance
    export DOCKER_BUILDKIT=1
    CACHE_ARGS+=(
        --build-arg BUILDKIT_INLINE_CACHE=1
    )
fi

# Build the image
docker build \
    "${CACHE_ARGS[@]}" \
    -f "$DOCKERFILE" \
    -t "${IMAGE_NAME}:${IMAGE_TAG}" \
    --build-arg APP_VERSION="${IMAGE_TAG}" \
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
    echo ""
    echo "Build options:"
    echo "  Force fresh build: USE_CACHE=false ./build.sh ${IMAGE_TAG}"
else
    echo ""
    echo "Build failed!"
    exit 1
fi
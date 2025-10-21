#!/bin/bash
set -e

# Configuration
ENVIRONMENT="${1:-prod}"  # prod or dev
IMAGE_NAME="ships-app"
IMAGE_TAG="latest"
CONTAINER_NAME="ships-app"
HOST_PORT="6969"
VOLUME_PATH="./storage/database"

# Set environment-specific config
if [ "$ENVIRONMENT" = "dev" ]; then
    ENV_FILE=".env"
    CONTAINER_NAME="ships-app-dev"
    HOST_PORT="8080"
elif [ "$ENVIRONMENT" = "prod" ]; then
    ENV_FILE=".env.production"
    CONTAINER_NAME="ships-app"
    HOST_PORT="6969"
else
    echo "Error: Invalid environment. Use 'prod' or 'dev'"
    echo "Usage: ./deploy.sh [prod|dev]"
    exit 1
fi

echo "Deploying Ships App (${ENVIRONMENT})"
echo "================================================"
echo "Using: $ENV_FILE"
echo "Port: $HOST_PORT"
echo ""

# Check if env file exists
if [ ! -f "$ENV_FILE" ]; then
    echo "Error: Environment file not found: $ENV_FILE"
    echo "Create $ENV_FILE in project root"
    exit 1
fi

# Check if image exists
if ! docker images "${IMAGE_NAME}:${IMAGE_TAG}" | grep -q "${IMAGE_NAME}"; then
    echo "Error: Docker image ${IMAGE_NAME}:${IMAGE_TAG} not found!"
    echo "Run ./build.sh first"
    exit 1
fi

# Stop and remove old container if exists
if docker ps -a --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo "Stopping old container..."
    docker stop "$CONTAINER_NAME" || true
    echo "Removing old container..."
    docker rm "$CONTAINER_NAME" || true
fi

# Create volume directory if it doesn't exist
mkdir -p "$VOLUME_PATH"

# Deploy new container
echo "Starting new container..."
docker run -d \
  --name "$CONTAINER_NAME" \
  --env-file "$ENV_FILE" \
  -p "${HOST_PORT}:80" \
  -v "$(pwd)/${VOLUME_PATH}:/var/www/html/storage/database" \
  --restart unless-stopped \
  "${IMAGE_NAME}:${IMAGE_TAG}"

# Wait for container to be healthy
echo "Waiting for container to start..."
sleep 3

# Check if container is running
if docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo ""
    echo "Deployment successful!"
    echo "================================================"
    echo "Container Status:"
    docker ps --filter "name=${CONTAINER_NAME}" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
    echo ""
    echo "Your app is running at: http://localhost:${HOST_PORT}"
    echo ""
    echo "Useful commands:"
    echo "  View logs:       docker logs -f ${CONTAINER_NAME}"
    echo "  Enter container: docker exec -it ${CONTAINER_NAME} sh"
    echo "  Restart:         docker restart ${CONTAINER_NAME}"
    echo "  Stop:            docker stop ${CONTAINER_NAME}"
    echo "  Clear cache:     docker exec ${CONTAINER_NAME} php artisan config:clear"
else
    echo ""
    echo "Deployment failed! Container is not running."
    echo "Check logs: docker logs ${CONTAINER_NAME}"
    exit 1
fi
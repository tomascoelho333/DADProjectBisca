# Bisca Project - School Server Deployment Guide

This guide provides step-by-step instructions to deploy the Bisca platform to the school's Kubernetes cluster based on the official DAD deployment tutorial.

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Project Structure Preparation](#project-structure-preparation)
3. [Environment Configuration](#environment-configuration)
4. [Docker Configuration](#docker-configuration)
5. [Building and Pushing Container Images](#building-and-pushing-container-images)
6. [Deploying to Kubernetes](#deploying-to-kubernetes)
7. [Running Commands on the Cluster](#running-commands-on-the-cluster)
8. [Updating Applications](#updating-applications)
9. [Troubleshooting](#troubleshooting)

---

## Prerequisites

Before starting deployment, ensure you have:

### Required Software
- **Docker** - For building container images
- **kubectl** - Kubernetes command-line tool
- **VPN Access** - Connected to the school network (required for cluster access)
- **Kubernetes Config File** - Obtained via email from school

### Setup Steps

#### 1. Install kubectl
Follow instructions at: https://dad-tutorials.vercel.app/tools

#### 2. Configure Kubernetes Access
```bash
# Create .kube directory if it doesn't exist
mkdir -p ~/.kube

# Copy the config file you received via email to ~/.kube/
# Rename it to 'config' (no extension)
cp /path/to/received/config ~/.kube/config

# Test the connection
kubectl get pods
```

#### 3. Verify Docker Installation
```bash
docker --version
docker ps
```

### ✅ Prerequisites Checklist
- [ ] Docker installed and running
- [ ] kubectl installed and accessible
- [ ] Kubernetes config file in ~/.kube/config
- [ ] Can run `kubectl get pods` successfully
- [ ] Connected to school VPN (if required)

---

## Project Structure Preparation

Your project should have the following structure for deployment:

```
DADProjectBisca/                    (Project Base)
├── deployment/                     (Deployment files - NEEDS TO BE CREATED)
│   ├── DockerfileLaravel          (Laravel container definition)
│   ├── DockerfileVue              (Vue container definition)
│   ├── DockerfileWS               (WebSocket container definition)
│   ├── laravel-deployment.yaml    (K8s Laravel resource)
│   ├── vue-deployment.yaml        (K8s Vue resource)
│   ├── ws-deployment.yaml         (K8s WebSocket resource)
│   ├── laravel-service.yaml       (K8s Laravel service)
│   ├── vue-service.yaml           (K8s Vue service)
│   └── ws-service.yaml            (K8s WebSocket service)
├── bisca-api/                     (Laravel Project Root)
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── routes/
│   └── ...
├── bisca-client/                  (Vue Project Root)
│   ├── src/
│   ├── public/
│   ├── vite.config.js
│   └── ...
└── websockets/                    (Node WebSocket Project Root)
    ├── server.js
    ├── events/
    └── ...
```

### ⚠️ Important: Deployment Folder

The `deployment/` folder is **NOT included in the project**. You need to create it with:
- Dockerfiles for each service
- Kubernetes YAML resource files

**Refer to the [DAD Tutorials repository](https://github.com/ricardogomes/DAD-Tutorials/tree/main/code/deployment) for these files.**

### Your Group Number

Throughout this guide, replace:
- `dad-group-X` with your actual group number (e.g., `dad-group-2`)
- `{{group}}` with your group ID (e.g., `group-2`)
- `{{version}}` with version numbers (e.g., `1.0.0`, `1.0.1`)

---

## Environment Configuration

### Vue.js (.env files)

Create `.env` and `.env.production` files in the `bisca-client/` directory:

#### `.env` (Local Development)
```ini
VITE_API_DOMAIN=localhost:8000
VITE_WS_CONNECTION=ws://localhost:3000
```

#### `.env.production` (Production/School Server)
```ini
VITE_API_DOMAIN=api-dad-group-X.172.22.21.253.sslip.io
VITE_WS_CONNECTION=ws://ws-dad-group-X.172.22.21.253.sslip.io
```

Replace `X` with your group number (e.g., `api-dad-group-2.172.22.21.253.sslip.io`)

### Laravel (.env Configuration)

Ensure `bisca-api/.env` is properly configured for production:

```bash
APP_NAME=Bisca
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api-dad-group-X.172.22.21.253.sslip.io

DB_CONNECTION=sqlite
DB_DATABASE=/app/database/database.sqlite

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_DRIVER=sync
```

### Node.js WebSocket Configuration

Ensure `websockets/index.js` has proper configuration for the cluster environment:
- Correct port configuration (typically 3000)
- CORS settings for your domains
- Connection handling for multiple instances

---

## Docker Configuration

### Configure Docker to Use Insecure Registry

The school's Docker registry uses HTTP only (no HTTPS), so you must configure Docker to allow insecure connections.

#### Option 1: Docker Desktop (Windows/MacOS)

1. Open **Docker Desktop Settings**
2. Go to **Docker Engine**
3. Add the following JSON:
```json
{
  "insecure-registries" : [ "registry-172.22.21.115.sslip.io" ]
}
```
4. Click **Apply & Restart**

#### Option 2: Linux (edit daemon.json directly)

```bash
# Edit the Docker daemon configuration
sudo nano /etc/docker/daemon.json

# Add or update the insecure-registries section:
{
  "insecure-registries" : [ "registry-172.22.21.115.sslip.io" ]
}

# Save and restart Docker
sudo systemctl restart docker
```

### Verify Docker Configuration

```bash
docker login -u your-username -p your-password registry-172.22.21.115.sslip.io
```

---

## Building and Pushing Container Images

### Step 1: Build All Container Images

Run these commands from the **project root directory** (DADProjectBisca/):

#### Build Laravel API Image
```bash
docker build -t registry-172.22.21.115.sslip.io/dad-group-X/api:v1.0.0 \
    --platform linux/amd64 \
    -f ./deployment/DockerfileLaravel \
    ./bisca-api
```

#### Build Vue Frontend Image
```bash
docker build -t registry-172.22.21.115.sslip.io/dad-group-X/web:v1.0.0 \
    --platform linux/amd64 \
    -f ./deployment/DockerfileVue \
    ./bisca-client
```

#### Build WebSocket Server Image
```bash
docker build -t registry-172.22.21.115.sslip.io/dad-group-X/ws:v1.0.0 \
    --platform linux/amd64 \
    -f ./deployment/DockerfileWS \
    ./websockets
```

### Step 2: Verify Images Were Built

```bash
docker images | grep registry-172.22.21.115.sslip.io/dad-group-X
```

You should see three images:
- `api:v1.0.0`
- `web:v1.0.0`
- `ws:v1.0.0`

### Step 3: Push Images to Container Registry

#### Push Laravel API Image
```bash
docker push registry-172.22.21.115.sslip.io/dad-group-X/api:v1.0.0
```

#### Push Vue Frontend Image
```bash
docker push registry-172.22.21.115.sslip.io/dad-group-X/web:v1.0.0
```

#### Push WebSocket Server Image
```bash
docker push registry-172.22.21.115.sslip.io/dad-group-X/ws:v1.0.0
```

### ⏱️ Expected Time
Building and pushing images takes **10-30 minutes** depending on your internet connection.

---

## Deploying to Kubernetes

### Step 1: Replace Group ID in Kubernetes Files

Before deploying, replace all instances of `dad-group-x` with your actual group ID in all YAML files:

```bash
# From project root, this command replaces all occurrences in deployment folder
# (Adjust case sensitivity as needed for your system)
find ./deployment -name "*.yaml" -type f -exec sed -i 's/dad-group-x/dad-group-X/g' {} \;
```

Or manually edit each file in `deployment/`:
- `laravel-deployment.yaml`
- `vue-deployment.yaml`
- `ws-deployment.yaml`
- `laravel-service.yaml`
- `vue-service.yaml`
- `ws-service.yaml`

### Step 2: Deploy to Kubernetes

From the **project root directory**, run:

```bash
kubectl apply -f deployment/
```

This command:
- Creates your namespace (dad-group-X)
- Deploys all three services (API, Web, WebSocket)
- Sets up networking and load balancing

### Step 3: Verify Deployment

#### Check Deployment Status
```bash
kubectl get pods
```

Look for three running pods:
- `laravel-app-xxxx`
- `vue-app-xxxx`
- `websocket-server-xxxx`

All should eventually show status `Running` with `READY 1/1`.

#### Expected Pod Startup Sequence
```
NAME                                READY   STATUS              RESTARTS   AGE
laravel-app-5d7f8c9b-2k4vx         0/1     ContainerCreating   0          5s
vue-app-7c3k9m2-j5r8p              0/1     ImagePullBackOff    0          5s
websocket-server-9f2l3n-x8q2m      0/1     Pending              0          5s

# After 30-60 seconds (typically):
laravel-app-5d7f8c9b-2k4vx         1/1     Running             0          45s
vue-app-7c3k9m2-j5r8p              1/1     Running             0          50s
websocket-server-9f2l3n-x8q2m      1/1     Running             0          55s
```

### Step 4: Access Your Application

Once all pods are `Running`, your application is accessible at:

- **Frontend (Vue):** http://web-dad-group-X.172.22.21.253.sslip.io
- **API (Laravel):** http://api-dad-group-X.172.22.21.253.sslip.io
- **WebSocket:** ws://ws-dad-group-X.172.22.21.253.sslip.io

Replace `X` with your group number.

### ✅ Deployment Verification Checklist
- [ ] All three pods show `1/1 Running`
- [ ] `kubectl get pods` returns successfully
- [ ] Frontend URL is accessible
- [ ] API URL responds to health check
- [ ] WebSocket connection works

---

## Running Commands on the Cluster

### Initialize Database (First Deployment)

You need to run Laravel migrations and seeders on the deployed instance:

#### 1. Find the Laravel Pod Name
```bash
kubectl -n dad-group-X get pods -l app=laravel-app
```

This will show something like: `laravel-app-5d7f8c9b-2k4vx`

#### 2. Run Migration and Seeding
```bash
kubectl -n dad-group-X exec -it <pod-name> -- php artisan migrate:fresh --seed
```

Replace `<pod-name>` with the actual pod name from step 1.

#### Example:
```bash
kubectl -n dad-group-2 exec -it laravel-app-5d7f8c9b-2k4vx -- php artisan migrate:fresh --seed
```

### Running Other Laravel Commands

Generic syntax for any Laravel artisan command:
```bash
kubectl -n dad-group-X exec -it <pod-name> -- php artisan <command>
```

Examples:
```bash
# Create a new admin user
kubectl -n dad-group-X exec -it <pod-name> -- php artisan tinker

# Clear application cache
kubectl -n dad-group-X exec -it <pod-name> -- php artisan cache:clear

# View application logs
kubectl -n dad-group-X exec -it <pod-name> -- tail -f storage/logs/laravel.log
```

---

## Updating Applications

### ⚠️ Important: Version Management

When updating your application code, you **must bump the version number**. Kubernetes uses version tags to detect and deploy changes.

### Update Process

#### Step 1: Update Your Code
Make changes to your Laravel, Vue, or WebSocket code as needed.

#### Step 2: Increment Version Numbers
Decide on new version numbers following semantic versioning:
- `1.0.0` → `1.0.1` (patch/bug fix)
- `1.0.0` → `1.1.0` (minor feature)
- `1.0.0` → `2.0.0` (major change)

#### Step 3: Rebuild Images with New Version
```bash
# Rebuild all images with new version (e.g., 1.0.1)
docker build -t registry-172.22.21.115.sslip.io/dad-group-X/api:v1.0.1 \
    --platform linux/amd64 \
    -f ./deployment/DockerfileLaravel \
    ./bisca-api

docker build -t registry-172.22.21.115.sslip.io/dad-group-X/web:v1.0.1 \
    --platform linux/amd64 \
    -f ./deployment/DockerfileVue \
    ./bisca-client

docker build -t registry-172.22.21.115.sslip.io/dad-group-X/ws:v1.0.1 \
    --platform linux/amd64 \
    -f ./deployment/DockerfileWS \
    ./websockets
```

#### Step 4: Push New Images
```bash
docker push registry-172.22.21.115.sslip.io/dad-group-X/api:v1.0.1
docker push registry-172.22.21.115.sslip.io/dad-group-X/web:v1.0.1
docker push registry-172.22.21.115.sslip.io/dad-group-X/ws:v1.0.1
```

#### Step 5: Update Kubernetes YAML Files
Edit the YAML files in `deployment/` and update image versions:

In `laravel-deployment.yaml`:
```yaml
containers:
  - name: laravel-app
    image: registry-172.22.21.115.sslip.io/dad-group-X/api:v1.0.1  # Update version here
```

In `vue-deployment.yaml`:
```yaml
containers:
  - name: vue-app
    image: registry-172.22.21.115.sslip.io/dad-group-X/web:v1.0.1  # Update version here
```

In `ws-deployment.yaml`:
```yaml
containers:
  - name: websocket-server
    image: registry-172.22.21.115.sslip.io/dad-group-X/ws:v1.0.1  # Update version here
```

#### Step 6: Deploy Updated Resources
```bash
kubectl apply -f deployment/
```

#### Step 7: Verify Rollout
```bash
kubectl -n dad-group-X rollout status deployment/laravel-app
kubectl -n dad-group-X rollout status deployment/vue-app
kubectl -n dad-group-X rollout status deployment/websocket-server
```

### Quick Restart (Same Version)

If you need to restart pods without changing code (useful for debugging):

```bash
kubectl -n dad-group-X rollout restart deployment/laravel-app
kubectl -n dad-group-X rollout restart deployment/vue-app
kubectl -n dad-group-X rollout restart deployment/websocket-server
```

---

## Checking Logs

### View Pod Logs

#### Get All Pods
```bash
kubectl get pods
```

#### View Specific Pod Logs
```bash
kubectl logs <full-pod-name>
```

Example:
```bash
kubectl logs laravel-app-5d7f8c9b-2k4vx
```

#### View Logs in Real-Time
```bash
kubectl logs -f <full-pod-name>
```

#### View Logs from Specific Namespace
```bash
kubectl -n dad-group-X logs <pod-name>
```

### View Previous Logs (if pod crashed)
```bash
kubectl logs <pod-name> --previous
```

---

## Troubleshooting

### Issue: "Connection refused" - Cannot connect to cluster

**Solution:**
- Verify VPN is connected
- Check kubernetes config file: `ls -la ~/.kube/config`
- Test cluster access: `kubectl get pods`
- Verify kubectl is installed: `kubectl version`

### Issue: Docker build fails - "platform linux/amd64 not available"

**Solution:**
```bash
# Install buildx for multi-platform builds
docker buildx create --use

# Or rebuild without platform specification (not recommended for cluster)
docker build -t registry-172.22.21.115.sslip.io/dad-group-X/api:v1.0.0 \
    -f ./deployment/DockerfileLaravel \
    ./bisca-api
```

### Issue: "ImagePullBackOff" - Pods stuck in ImagePullBackOff

**Solution:**
- Verify images were pushed: `docker images | grep dad-group`
- Check Docker registry login: `docker login registry-172.22.21.115.sslip.io`
- Check image name in YAML matches pushed image
- Verify platform is `linux/amd64` in build command

### Issue: Pod crashes immediately after deployment

**Solution:**
1. Check pod logs: `kubectl logs <pod-name>`
2. Verify database initialization: `kubectl -n dad-group-X exec -it <laravel-pod> -- php artisan migrate:fresh --seed`
3. Check Laravel .env configuration
4. Verify all environment variables are set

### Issue: "Cannot pull image from registry" - Network/Registry Issue

**Solution:**
- Verify insecure-registries configuration: `docker info`
- Test registry connectivity: `docker login registry-172.22.21.115.sslip.io`
- Restart Docker service
- Check if connected to school network/VPN

### Issue: WebSocket connection fails on production

**Solution:**
1. Verify WebSocket pod is running: `kubectl -n dad-group-X get pods -l app=websocket-server`
2. Check WebSocket pod logs: `kubectl logs <ws-pod-name>`
3. Verify Vue.env.production has correct WebSocket URL
4. Rebuild Vue image with new configuration
5. Check CORS settings in WebSocket server configuration

### Issue: "dad-group-x" string still appears in deployments

**Solution:**
1. Find remaining instances: `grep -r "dad-group-x" deployment/`
2. Replace all occurrences: `find deployment/ -name "*.yaml" -exec sed -i 's/dad-group-x/dad-group-X/g' {} \;`
3. Reapply deployments: `kubectl apply -f deployment/`

### Issue: Port conflicts or service not accessible

**Solution:**
1. Check services: `kubectl -n dad-group-X get svc`
2. Check ingress/routing: `kubectl -n dad-group-X get ingress`
3. Verify domain resolves: `nslookup web-dad-group-X.172.22.21.253.sslip.io`
4. Check pod port configuration in YAML files

---

## Deployment Checklist

### Pre-Deployment
- [ ] All code is committed and tested locally
- [ ] Docker is installed and configured
- [ ] kubectl is installed and configured
- [ ] Kubernetes config file is in ~/.kube/config
- [ ] Connected to school VPN
- [ ] Group ID assigned (e.g., dad-group-2)
- [ ] Deployment folder exists with all necessary files
- [ ] .env files are properly configured (Vue and Laravel)

### Deployment
- [ ] Insecure registry configured in Docker
- [ ] All three Docker images built successfully
- [ ] All three images pushed to registry successfully
- [ ] All YAML files have correct group ID (no dad-group-x)
- [ ] `kubectl apply -f deployment/` executed successfully
- [ ] All pods showing 1/1 Running status
- [ ] Database migrations run successfully
- [ ] Frontend URL is accessible
- [ ] API URL is accessible
- [ ] WebSocket connection works

### Post-Deployment
- [ ] Test all admin features work correctly
- [ ] Test player functionality
- [ ] Check logs for any errors
- [ ] Monitor pod stability
- [ ] Document deployment time and any issues

---

## Version History

Keep track of your deployments:

| Version | Date | Changes | Status |
|---------|------|---------|--------|
| 1.0.0 | YYYY-MM-DD | Initial deployment | ✅ Deployed |
| 1.0.1 | YYYY-MM-DD | Bug fixes | ✅ Deployed |
| | | | |

---

## Additional Resources

- **DAD Tutorials:** https://dad-tutorials.vercel.app/deploy
- **Docker Documentation:** https://docs.docker.com/
- **Kubernetes Documentation:** https://kubernetes.io/docs/
- **Tutorial Repository:** https://github.com/ricardogomes/DAD-Tutorials/tree/main/code/deployment

---

## Support

If you encounter issues:

1. Check this guide's Troubleshooting section
2. Review the DAD Tutorials: https://dad-tutorials.vercel.app/deploy
3. Check pod logs: `kubectl logs <pod-name>`
4. Contact your instructor or the DAD Tutorials author

---

**Good luck with your deployment! 🚀**

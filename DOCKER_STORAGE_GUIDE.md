# Aspen Discovery Docker Storage Configuration Guide

This guide explains how to deploy Aspen Discovery with Docker using different storage backends for user-uploaded data.

## Overview

Aspen Discovery separates different types of data:

- **Application Code**: Read-only, part of the Docker image
- **Configuration**: Site-specific settings (`/usr/local/aspen-discovery/sites/${SITE_NAME}`)
- **Database**: MySQL/MariaDB data (`/var/lib/mysql`)
- **Logs**: Application logs (`/var/log/aspen-discovery/${SITE_NAME}`)
- **Data Directory**: All persistent data including user uploads (`/data/aspen-discovery/${SITE_NAME}`)

## Data Directory Structure

All persistent data is stored in `/data/aspen-discovery/${SITE_NAME}/`:

```
/data/aspen-discovery/${SITE_NAME}/
├── images/                 # User-uploaded images
│   ├── web_builder/
│   │   ├── full/
│   │   ├── x-large/
│   │   ├── large/
│   │   ├── medium/
│   │   └── small/
│   └── rewards/
│       └── full/
├── files/                  # User-uploaded files
│   ├── web_builder_pdf/
│   └── record_pdfs/
├── fonts/                  # User-uploaded fonts
├── covers/                 # User-uploaded covers
│   ├── original/
│   ├── thumbnail/
│   └── medium/
├── solr/                   # Solr indexes (system data)
├── marc/                   # MARC records (system data)
└── ... (other system data)
```

## Storage Backend Options

### Option 1: Local Storage (Default)

Best for: Development, single-server deployments, or when using network-attached storage.

#### Environment Configuration

Create a `.env` file:

```bash
# Basic configuration
SITE_NAME=demo
ASPEN_DATA_DIR=./data

# Storage backend
ASPEN_STORAGE_BACKEND=local

# Local storage paths (optional - defaults shown)
ASPEN_USER_DATA_PATH=/data/aspen-discovery/${SITE_NAME}
ASPEN_ASSETS_PATH=/usr/local/aspen-discovery/code/web/assets
ASPEN_TEMP_PATH=/tmp/aspen-uploads
```

#### Docker Compose Usage

```bash
# Start with local storage
docker-compose up -d

# The data will be stored in:
# ./data/data/ (on your host)
# /data/aspen-discovery/${SITE_NAME}/ (in container)
```

#### Directory Structure Created

```
${ASPEN_DATA_DIR}/         # Host directory (e.g., ./aspen-data/)
├── conf/                  # Site configuration
├── data/                  # ALL persistent data (user uploads + system data)
│   ├── images/           # User-uploaded images
│   ├── files/            # User-uploaded files
│   ├── fonts/            # User-uploaded fonts
│   ├── covers/           # User-uploaded covers
│   ├── solr/             # System: Solr indexes (data)
│   └── marc/             # System: MARC records
├── solr/                 # Solr configuration
├── logs/                 # Application logs
└── database/             # MySQL data
```

### Option 2: Amazon S3 Storage

Best for: Production deployments, multi-server setups, automatic backups, CDN integration.

#### Environment Configuration

Create a `.env` file:

```bash
# Basic configuration
SITE_NAME=demo
ASPEN_DATA_DIR=./data

# Storage backend
ASPEN_STORAGE_BACKEND=s3

# S3 configuration
ASPEN_S3_BUCKET=my-aspen-uploads
ASPEN_S3_REGION=us-east-1
ASPEN_S3_ACCESS_KEY=AKIAIOSFODNN7EXAMPLE
ASPEN_S3_SECRET_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY

# Optional: Custom S3 endpoint (for S3-compatible services)
ASPEN_S3_ENDPOINT=https://s3.amazonaws.com

# Local paths (only for temporary files and static assets)
ASPEN_ASSETS_PATH=/usr/local/aspen-discovery/code/web/assets
ASPEN_TEMP_PATH=/tmp/aspen-uploads
```

#### S3 Bucket Structure

Your S3 bucket will contain only user-uploaded content:

```
my-aspen-uploads/
├── images/
│   ├── web_builder/
│   └── rewards/
├── files/
├── fonts/
└── covers/
```

System data (Solr indexes, MARC records, etc.) remains local in `/data/aspen-discovery/${SITE_NAME}/`.

#### Docker Compose Usage

```bash
# Start with S3 storage
docker-compose up -d

# User uploads go to S3
# System data remains in ./data/data/
```

### Option 3: Google Cloud Storage

Best for: Google Cloud Platform deployments, integration with other Google services.

#### Environment Configuration

Create a `.env` file:

```bash
# Basic configuration
SITE_NAME=demo
ASPEN_DATA_DIR=./data

# Storage backend
ASPEN_STORAGE_BACKEND=gcs

# Google Cloud Storage configuration
ASPEN_GCS_BUCKET=my-aspen-uploads
ASPEN_GCS_PROJECT_ID=my-project-id

# Service account key file (mount as volume)
ASPEN_GCS_KEY_FILE=/etc/gcs/service-account.json

# Local paths (only for temporary files and static assets)
ASPEN_ASSETS_PATH=/usr/local/aspen-discovery/code/web/assets
ASPEN_TEMP_PATH=/tmp/aspen-uploads
```

#### Additional Volume for GCS

You'll need to mount your service account key:

```yaml
volumes:
  - ./gcs-service-account.json:/etc/gcs/service-account.json:ro
```

## Deployment Instructions

### 1. Choose Your Storage Backend

Copy the appropriate environment configuration above to your `.env` file.

### 2. Prepare Storage (if using cloud)

#### For S3:
```bash
# Create S3 bucket
aws s3 mb s3://my-aspen-uploads

# Set appropriate permissions
aws s3api put-bucket-policy --bucket my-aspen-uploads --policy file://bucket-policy.json
```

#### For Google Cloud Storage:
```bash
# Create GCS bucket
gsutil mb gs://my-aspen-uploads

# Create service account and download key
gcloud iam service-accounts create aspen-storage
gcloud projects add-iam-policy-binding PROJECT_ID \
    --member="serviceAccount:aspen-storage@PROJECT_ID.iam.gserviceaccount.com" \
    --role="roles/storage.admin"
```

### 3. Start the Application

```bash
# Start all services
docker-compose up -d

# Check logs
docker-compose logs -f backend

# Initialize storage directories (first run only)
docker-compose exec backend php -r "
require_once '/usr/local/aspen-discovery/code/web/bootstrap.php';
\$storage = StorageManager::getInstance();
\$storage->initializeStorage();
"
```

### 4. Verify Storage

#### Local Storage:
```bash
# Check local directories
ls -la ./data/

# Should show: images/ files/ fonts/ covers/ solr/ marc/ etc.
```

#### Cloud Storage:
```bash
# For S3 (only user uploads)
aws s3 ls s3://my-aspen-uploads/

# For GCS (only user uploads)
gsutil ls gs://my-aspen-uploads/

# System data remains local
ls -la ./data/solr/
```

## Migration Between Storage Backends

### From Local to Cloud

```bash
# 1. Stop the application
docker-compose down

# 2. Update .env file with cloud storage settings

# 3. Migrate only user-uploaded data
# For S3:
aws s3 sync ${ASPEN_DATA_DIR}/data/images/ s3://my-aspen-uploads/images/
aws s3 sync ${ASPEN_DATA_DIR}/data/files/ s3://my-aspen-uploads/files/
aws s3 sync ${ASPEN_DATA_DIR}/data/fonts/ s3://my-aspen-uploads/fonts/
aws s3 sync ${ASPEN_DATA_DIR}/data/covers/ s3://my-aspen-uploads/covers/

# For GCS:
gsutil -m rsync -r ${ASPEN_DATA_DIR}/data/images/ gs://my-aspen-uploads/images/
gsutil -m rsync -r ${ASPEN_DATA_DIR}/data/files/ gs://my-aspen-uploads/files/
gsutil -m rsync -r ${ASPEN_DATA_DIR}/data/fonts/ gs://my-aspen-uploads/fonts/
gsutil -m rsync -r ${ASPEN_DATA_DIR}/data/covers/ gs://my-aspen-uploads/covers/

# 4. Start with new backend
docker-compose up -d
```

### From Cloud to Local

```bash
# 1. Stop the application
docker-compose down

# 2. Download user data from cloud
# For S3:
aws s3 sync s3://my-aspen-uploads/images/ ${ASPEN_DATA_DIR}/data/images/
aws s3 sync s3://my-aspen-uploads/files/ ${ASPEN_DATA_DIR}/data/files/
aws s3 sync s3://my-aspen-uploads/fonts/ ${ASPEN_DATA_DIR}/data/fonts/
aws s3 sync s3://my-aspen-uploads/covers/ ${ASPEN_DATA_DIR}/data/covers/

# For GCS:
gsutil -m rsync -r gs://my-aspen-uploads/images/ ${ASPEN_DATA_DIR}/data/images/
gsutil -m rsync -r gs://my-aspen-uploads/files/ ${ASPEN_DATA_DIR}/data/files/
gsutil -m rsync -r gs://my-aspen-uploads/fonts/ ${ASPEN_DATA_DIR}/data/fonts/
gsutil -m rsync -r gs://my-aspen-uploads/covers/ ${ASPEN_DATA_DIR}/data/covers/

# 3. Update .env file for local storage
# 4. Start application
docker-compose up -d
```

## Backup Strategies

### Local Storage
```bash
# Backup all data (user uploads + system data)
tar -czf aspen-data-$(date +%Y%m%d).tar.gz ./data/

# Backup only user uploads
tar -czf aspen-user-uploads-$(date +%Y%m%d).tar.gz \
  ./data/images/ \
  ./data/files/ \
  ./data/fonts/ \
  ./data/covers/

# Restore
tar -xzf aspen-data-20240125.tar.gz
```

### Cloud Storage
```bash
# S3 - Enable versioning and cross-region replication
aws s3api put-bucket-versioning --bucket my-aspen-uploads --versioning-configuration Status=Enabled

# GCS - Enable object versioning
gsutil versioning set on gs://my-aspen-uploads

# System data still needs local backup
tar -czf aspen-system-data-$(date +%Y%m%d).tar.gz \
  --exclude='./data/images' \
  --exclude='./data/files' \
  --exclude='./data/fonts' \
  --exclude='./data/covers' \
  ./data/
```

## Troubleshooting

### Check Storage Configuration
```bash
docker-compose exec backend php -r "
require_once '/usr/local/aspen-discovery/code/web/bootstrap.php';
\$storage = StorageManager::getInstance();
print_r(\$storage->getConfiguration());
"
```

### Test File Upload
```bash
# Create test file
docker-compose exec backend touch /tmp/test.txt

# Test storage
docker-compose exec backend php -r "
require_once '/usr/local/aspen-discovery/code/web/bootstrap.php';
\$storage = StorageManager::getInstance();
\$result = \$storage->storeFile('/tmp/test.txt', \$storage->getUserDataPath('images', 'test', 'test.txt'));
echo \$result ? 'SUCCESS' : 'FAILED';
"
```

### Common Issues

1. **Permission Denied**: Check file permissions and Docker user
2. **S3 Access Denied**: Verify AWS credentials and bucket policies
3. **GCS Authentication**: Ensure service account key is properly mounted
4. **Disk Space**: Monitor local storage usage, especially for system data

## Performance Considerations

- **Local Storage**: Fastest for read/write, but limited by disk I/O
- **S3**: Good for user uploads, consider CloudFront CDN for better performance
- **GCS**: Similar to S3, can integrate with Google CDN
- **Hybrid**: User uploads in cloud, system data local for best performance

## Security Best Practices

1. **Use IAM roles** instead of access keys when possible
2. **Enable encryption** at rest for cloud storage
3. **Set proper bucket policies** to restrict access
4. **Regular security audits** of storage permissions
5. **Monitor access logs** for unusual activity
6. **Separate user uploads from system data** for better security isolation

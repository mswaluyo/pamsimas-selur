# Panduan Deployment PAMSIMAS ke Google Cloud Run
# ================================================
# Free Tier: 2 juta request/bulan, 360K GB-detik, 180K vCPU-detik
# ================================================

## PERSYARATAN
- Akun Google (gmail)
- Google Cloud Console: https://console.cloud.google.com
- Project baru di Google Cloud

## LANGKAH 1: Setup Google Cloud Project

1. Buka **https://console.cloud.google.com**
2. Buat project baru: `pamsimas-selur`
3. Enable billing (perlu kartu kredit untuk verifikasi, TIDAK dikenakan biaya untuk free tier)
4. Enable APIs:
   - Cloud Run API
   - Cloud Build API
   - Container Registry API

## LANGKAH 2: Install Google Cloud SDK

Di terminal VS Code:

```powershell
# Install gcloud SDK
iwr -useb https://dl.google.com/dl/cloudsdk/channels/rapid/GoogleCloudSDKInstaller.exe | iex

# Atau download manual dari: https://cloud.google.com/sdk/docs/install

# Initialize
gcloud init

# Login
gcloud auth login

# Set project
gcloud config set project pamsimas-selur
```

## LANGKAH 3: Deploy ke Cloud Run

### Opsi A: Deploy Langsung (Simple)

```powershell
# Build dan deploy
gcloud run deploy pamsimas-selur \
  --source . \
  --region us-central1 \
  --platform managed \
  --allow-unauthenticated \
  --memory 512Mi \
  --set-env-vars="APP_ENV=production,APP_DEBUG=false,APP_KEY=base64:DSZPTs8ms9URWNBXLho/DmC9pQDV67lk9pGLqTUP3jE=,DB_CONNECTION=sqlite"
```

### Opsi B: Via Cloud Build (Recommended)

```powershell
# Submit build
gcloud builds submit --config cloudbuild.yaml

# Atau deploy dari Container Registry
gcloud run deploy pamsimas-selur \
  --image gcr.io/PROJECT_ID/pamsimas-selur \
  --region us-central1 \
  --platform managed \
  --allow-unauthenticated
```

## LANGKAH 4: Set Environment Variables

Setelah deploy, update env vars:

```powershell
gcloud run services update pamsimas-selur \
  --region us-central1 \
  --set-env-vars="APP_KEY=base64:DSZPTs8ms9URWNBXLho/DmC9pQDV67lk9pGLqTUP3jE=,WA_GATEWAY_SECRET=pamsimas-secret-key-123,DEVICE_API_KEY=P4mS1m4s-T1rt0-Arg0-2025,RUN_MIGRATIONS=true"
```

## CATATAN PENTING

⚠️ **Cloud Run adalah stateless** — SQLite tidak akan persist antara restart.
Untuk produksi, gunakan **Cloud SQL** (MySQL gratis tier 10GB).

⚠️ **Free Tier Cloud Run:**
- 2 juta request/bulan
- 360.000 GB-detik memori/bulan
- 180.000 vCPU-detik/bulan

## ALTERNATIF: Google Compute Engine (Gratis Selamanya)

Jika butuh persistent storage, gunakan **Compute Engine e1-micro**:
- Gratis selamanya di region us-west1, us-central1, us-east1
- 0.2 vCPU, 0.6GB RAM
- Install Docker dan jalankan container langsung

```powershell
# Buat VM
gcloud compute instances create pamsimas-vm \
  --machine-type e1-micro \
  --zone us-central1-a \
  --image-family debian-11 \
  --image-project debian-cloud

# SSH ke VM
gcloud compute ssh pamsimas-vm --zone us-central1-a

# Install Docker dan deploy
sudo apt update && sudo apt install docker.io -y
sudo docker run -d -p 80:8080 gcr.io/PROJECT_ID/pamsimas-selur
```

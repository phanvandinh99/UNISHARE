# Laravel Project with Docker

## 🚀 Quick Start

### 1. Clone repository

```bash
git clone https://github.com/phanvandinh99/UNISHARE
cd UNISHARE
```

### 2. Copy `.env` file

```bash
cp .env.example .env
```

### 3. Start Docker containers

```bash
docker-compose up -d
```

### 4. Install Composer dependencies

```bash
docker exec -it app composer install
```

### 5. Generate application key

```bash
php artisan key:generate
```

### 6. Run migrations

```bash
php artisan migrate
```

```bash
php artisan migrate:fresh --seed
```

Seed only:

```bash
php artisan db:seed
```

### 7. View route list

```bash
php artisan route:list
```

### 8. Serve application

```bash
php artisan serve --port=8001
```

👉 http://localhost:8001

---

## 🐳 Docker Commands

-   Start containers: `docker-compose up -d`
-   Stop containers: `docker-compose down`
-   Enter container: `docker exec -it app bash`

---

## 🧪 Running Tests

```bash
php artisan test
```

---

## 🧹 Useful Artisan Commands

-   Clear config cache:

```bash
php artisan config:clear
```

-   Clear route cache:

```bash
php artisan route:clear
```

---

## 🙋 Troubleshooting

-   Ensure Docker is running
-   Check `.env` configuration (DB connection especially)
-   If you see permission issues, try:

```bash
sudo chmod -R 775 storage bootstrap/cache
```

---

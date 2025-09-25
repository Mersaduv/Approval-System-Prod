# 📧 Queue Worker راهنمای کامل

## 🚀 راه‌اندازی Queue Worker

### **روش 1: Windows (توصیه شده)**
```bash
# اجرای دستی
php artisan queue:work --tries=3 --timeout=60 --memory=128

# یا استفاده از فایل batch
start_queue_worker.bat
```

### **روش 2: Linux/Mac**
```bash
# اجرای دستی
php artisan queue:work --tries=3 --timeout=60 --memory=128

# یا استفاده از فایل shell
chmod +x start_queue_worker.sh
./start_queue_worker.sh
```

### **روش 3: Background (Linux/Mac)**
```bash
# اجرا در background
nohup php artisan queue:work --tries=3 --timeout=60 --memory=128 > queue.log 2>&1 &

# بررسی وضعیت
ps aux | grep "queue:work"
```

## ⚙️ پارامترهای مهم

- `--tries=3` - حداکثر 3 بار تلاش برای هر job
- `--timeout=60` - timeout 60 ثانیه برای هر job
- `--memory=128` - حداکثر 128MB حافظه
- `--daemon` - اجرا در background (اختیاری)

## 🔍 بررسی وضعیت

### **بررسی Jobs در Queue:**
```bash
php artisan queue:work --once
```

### **بررسی Jobs باقی‌مانده:**
```sql
SELECT COUNT(*) FROM jobs;
```

### **Clear کردن Queue:**
```bash
php artisan queue:clear
```

## 📋 مراحل راه‌اندازی پروژه

### **1. شروع پروژه:**
```bash
php artisan serve
```

### **2. شروع Queue Worker (در terminal جداگانه):**
```bash
php artisan queue:work --tries=3 --timeout=60 --memory=128
```

### **3. تست سیستم:**
- درخواست جدید ایجاد کنید
- ایمیل‌ها فوراً ارسال می‌شوند

## ⚠️ نکات مهم

1. **Queue Worker باید همیشه روشن باشد**
2. **اگر Queue Worker خاموش شود، ایمیل‌ها ارسال نمی‌شوند**
3. **Jobs در queue می‌مانند تا Queue Worker روشن شود**
4. **برای production از Supervisor استفاده کنید**

## 🛠️ عیب‌یابی

### **مشکل: ایمیل ارسال نمی‌شود**
```bash
# بررسی jobs در queue
php artisan queue:work --once

# اگر job وجود دارد، Queue Worker را روشن کنید
php artisan queue:work --tries=3
```

### **مشکل: Too many connections**
```bash
# Clear cache
php artisan config:clear
php artisan cache:clear

# Restart MySQL service
# Windows: net stop mysql && net start mysql
```

## ✅ وضعیت فعلی

- ✅ Queue Worker Service Provider غیرفعال شد
- ✅ Manual queue processing فعال است
- ✅ Scripts راه‌اندازی ایجاد شدند
- ✅ راهنمای کامل آماده است

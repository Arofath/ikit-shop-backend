# ប្រើប្រាស់ PHP ជំនាន់ 8.2 (អ្នកអាចដូរទៅ 8.1 តាមជំនាន់ Project អ្នកបាន)
FROM php:8.2-cli

# ដំឡើង Tools និង Packages ដែលចាំបាច់
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    libpq-dev \
    curl \
    && rm -rf /var/lib/apt/lists/*

# ដំឡើង PHP Extensions សម្រាប់ភ្ជាប់ទៅកាន់ Database MySQL (TiDB)
RUN docker-php-ext-install pdo pdo_mysql

# កំណត់ទីតាំងការងារក្នុង Docker
WORKDIR /app

# ចម្លងកូដ Project ទាំងអស់ចូលទៅក្នុង Docker
COPY . .

# ដំឡើង Composer និង Packages របស់ Laravel
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# ផ្តល់សិទ្ធិ (Permissions) ឲ្យ Laravel អាចសរសេរឯកសារបាន
RUN chmod -R 777 storage bootstrap/cache

# បញ្ជាឲ្យដំណើរការ Server ពេល Docker ដើរ
CMD php artisan serve --host=0.0.0.0 --port=$PORT
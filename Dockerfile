# ប្តូរមកប្រើ PHP ជំនាន់ 8.4 ឲ្យត្រូវនឹងតម្រូវការរបស់ Laravel ថ្មី
FROM php:8.4-cli

# ដំឡើង Tools និង Packages ដែលចាំបាច់
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# ដំឡើង pdo_mysql និង zip
RUN docker-php-ext-install pdo_mysql zip

# កំណត់ទីតាំងការងារក្នុង Docker
WORKDIR /app

# ចម្លងកូដ Project ទាំងអស់
COPY . .

# ដំឡើង Composer 
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# ផ្តល់សិទ្ធិ
RUN chmod -R 777 storage bootstrap/cache

# បញ្ជាឲ្យដំណើរការ Server
CMD php artisan serve --host=0.0.0.0 --port=$PORT
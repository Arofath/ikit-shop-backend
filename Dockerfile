FROM php:8.2-cli

# ដំឡើង Tools និង Packages ដែលចាំបាច់ (ថែម libzip-dev សម្រាប់ zip)
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# ដំឡើងតែ pdo_mysql និង zip បានហើយ (មិនបាច់ដាក់ pdo ទេ)
RUN docker-php-ext-install pdo_mysql zip

# កំណត់ទីតាំងការងារក្នុង Docker
WORKDIR /app

# ចម្លងកូដ Project ទាំងអស់ចូលទៅក្នុង Docker
COPY . .

# ដំឡើង Composer និង Packages របស់ Laravel
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# ផ្តល់សិទ្ធិ (Permissions)
RUN chmod -R 777 storage bootstrap/cache

# បញ្ជាឲ្យដំណើរការ Server
CMD php artisan serve --host=0.0.0.0 --port=$PORT
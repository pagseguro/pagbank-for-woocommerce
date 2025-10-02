# Install dependencies
composer install
pnpm install

# Build assets
pnpm run build

# Install WordPress
docker compose run --rm wordpress-cli wp core install --url=wordpress.localhost --title="WordPress Demo" --admin_name=wordpress --admin_password=wordpress --admin_email=you@example.com

# Setup permalinks
docker compose run --rm wordpress-cli wp rewrite structure '/%postname%/'

# Install plugins
docker compose run --rm wordpress-cli wp plugin install woocommerce woocommerce-extra-checkout-fields-for-brazil --activate

# Activate plugin
docker compose run --rm wordpress-cli wp plugin activate pagbank-for-woocommerce

# Delete plugins
docker compose run --rm wordpress-cli wp plugin delete akismet hello

# Install theme
docker compose run --rm wordpress-cli wp theme install storefront --activate

# Remove themes
docker compose run --rm wordpress-cli wp theme delete twentynineteen twentytwenty twentytwentyone twentytwentytwo twentytwentythree

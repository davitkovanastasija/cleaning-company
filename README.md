# Starting the application

Because we don't need the app running all the time, we can use one-off containers, which start, execute the given commands and shut down immediately. This way we are mindful about the computing resources while still being able to run the application in all environments which is the purpose of using Docker.

```
# Copy the .env.example to .env
cp .env.example .env
# Install composer
docker run --rm -it --volume $PWD:/app composer install
# Set the application key, needed for various Laravel-based tasks
docker run --rm -it --volume $PWD:/app php:8.1-cli php /app/artisan key:generate
# Run this command, everytime we want the file containing the schedules generated. It will create the file in: /storage/app
docker run --rm -it --volume $PWD:/app php:8.1-cli php /app/artisan generate:schedule
```

Whenever we need to create the file containing schedules again, we can use the following command. The file will be stored in /storage/app
```
docker run --rm -it --volume $PWD:/app php:8.1-cli php /app/artisan generate:schedule
```

When running the command, we can add value to the optional argument {email}. If an email address is provided and valid, the generated csv file will be send as an attachment (This applies only if we have a mail service configured in .env)

```
docker run --rm -it --volume $PWD:/app php:8.1-cli php /app/artisan generate:schedule johndoe@example.com
```
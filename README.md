# Laravel Breeze X

A simple version of [Laravel Breeze](https://github.com/laravel/breeze). 

Breeze offers a simple way to fully scaffold auth for the default guard and password broker. It does not offer any way to customise which guard/broker you want to scaffold. 
This package is aimed at the 1% of use cases where you want to quickly scaffold a "members", "admin" or any other area. 

It also removes the front-end consideration of how you style the forms - you can control this yourself using whatever system is best for your project. 



**Usage:**

```bash
php artisan breeze-x:install
```

This command will scaffold the controllers, request and routes for the default `web` guard and `users` password broker. 


```bash
php artisan breeze-x:install admin admins
```

This command is an example of how you would scaffold your application assuming you've configured an `admin` guard and `admins` password broker in your `config/auth.php` file. 


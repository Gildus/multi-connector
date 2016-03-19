# MultiConnector

It uses ADLDAP 5.0 library forked on Adldap2 (https://github.com/Adldap2/Adldap2) to create a bridge between Laravel and LDAP
> Originally written by Sarav. Adopted by the community.

## Installation
1. Install this package through Composer for Laravel v5.1:
    ```js
    composer require dgild/multi-connector:dev-master
    ```

1. Add the service provider in the app configuration by opening `config/app.php`, and add a new item to the providers array.

       ```
       Dgild\MultiConnector\MultiConnectorServiceProvider::class     
       ```
   Them you need to comment the line:
      ```
      Illuminate\Auth\AuthServiceProvider::class
      ```
   
1. Change the authentication driver in the Laravel config to use the ldap driver. You can find this in the following file `config/auth.php`

    ```php
    'driver' => 'eloquent',
    ```

	By

	```php
	'multi' => [
		'db' => [
			'driver' => 'eloquent',
			'model'  => App\User::class,
			'table'  => 'users'
		],
	    'ldap' => [
			'driver' => 'ldap',
			'model'  => Dgild\MultiConnector\Model\User::class,
			'table'  => 'users'
		],
	],
	```

1. Publish a new configuration file with `php artisan vendor:publish` in the configuration folder of Laravel you will find `config/ldap.php` and modify to your needs. For more detail of the configuration you can always check on [ADLAP documentation](http://adldap.sourceforge.net/wiki/doku.php?id=documentation_configuration)

    ```
    return array(
        'plugins' => array(
            'adldap' => array(
                'account_suffix'=>  '@domain.local',
                'domain_controllers'=>  array(
                    '192.168.0.1',
                    'dc02.domain.local'
                ), // Load balancing domain controllers
                'base_dn'   =>  'DC=domain,DC=local',
                'admin_username' => 'admin', // This is required for session persistance in the application
                'admin_password' => 'yourPassword',
            ),
        ),
    );
    ```

    Please note that the fields 'admin_username' and 'admin_password' are required for session persistance!

## Usage
The LDAP plugin is an extension of the Auth class and will act the same as normal usage with Eloquent driver.

For example if you need to login with Eloquent:
```
\Auth::attempt('db', $credentials, $request->has('remember'));
```
Or if you need to login with Ldap:
```
\Auth::attempt('ldap', $credentials, $request->has('remember'));
```

Them you need to modify /app/Http/Controllers/Auth/AuthController.php like these:

```
<?php
namespace App\Http\Controllers\Auth;

use App\User;
use Validator;
use App\Http\Controllers\Controller;
use Dgild\MultiConnector\Foundation\AuthenticatesUsers;

class AuthController extends Controller
{
    use  AuthenticatesUsers;

    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->username = 'username';
        $this->user = 'db';
        $this->saveUser = true;
        $this->middleware('guest', ['except' => 'getLogout']);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|confirmed|min:6',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);
    }
}

```


In the construct it's important these lines:

The property username is the field by login you can to change with email or another field of database for the login.
```
$this->username = 'username';
```

The property user is the type of connection or the type of adapter that you use in the login it can be ldap.
```
$this->user = 'db';
```

The property saveUser is a indicator if the user only exist in the database but not in database so the user was registered into database.
```
$this->saveUser = true;
```



## Notes

From the controller AuthController.php: https://github.com/laravel/laravel/blob/master/app/Http/Controllers/Auth/AuthController.php

Laravel documentation: [Authentication Quickstart](http://laravel.com/docs/master/authentication#authentication-quickstart)


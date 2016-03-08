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

From the controller AuthController.php: https://github.com/laravel/laravel/blob/master/app/Http/Controllers/Auth/AuthController.php
for example if you need to use the connection with db like these:

```
public function postLogin(Request $request)
{
    
    $this->validate($request, [
        'username' => 'required', 
        'password' => 'required',
    ]);

    // If the class is using the ThrottlesLogins trait, we can automatically throttle
    // the login attempts for this application. We'll key this by the username and
    // the IP address of the client making these requests into this application.
    
    $throttles = $this->isUsingThrottlesLoginsTrait();
    
    if ($throttles && $this->hasTooManyLoginAttempts($request)) {            
        return $this->sendLockoutResponse($request);
    }

    $credentials = $request->only('username', 'password');
    $logged = \Auth::attempt('db', $credentials, $request->has('remember'));

    if ($logged) {
        return $this->handleUserWasAuthenticated($request, $throttles);
    } 
    
    if ($throttles) {            
        $this->incrementLoginAttempts($request);
    }
    
    return view('auth.login')
        ->with('input', $request->only('username', 'remember'))
        ->withErrors(['username' => $this->getFailedLoginMessage()]);
}


protected function handleUserWasAuthenticated(Request $request, $throttles)
{        
    if ($throttles) {            
        $this->clearLoginAttempts($request);
    }
    
    if (method_exists($this, 'authenticated')) {            
        return $this->authenticated($request, Auth::user($this->user));
    }
    
    return redirect()->intended($this->redirectPath());
}

``` 
    

Laravel documentation: [Authentication Quickstart](http://laravel.com/docs/master/authentication#authentication-quickstart)


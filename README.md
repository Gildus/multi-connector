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

Now the login make a request to LDAP if now exist  its going to register into database them make a login with database, if exists in the database it's going to login with database again:

```
public function postLogin(Request $request)
{
    $this->validate($request, [
        $this->loginUsername() => 'required', 'password' => 'required',
    ]);

    // If the class is using the ThrottlesLogins trait, we can automatically throttle
    // the login attempts for this application. We'll key this by the username and
    // the IP address of the client making these requests into this application.
    $throttles = $this->isUsingThrottlesLoginsTrait();

    if ($throttles && $this->hasTooManyLoginAttempts($request)) {
        return $this->sendLockoutResponse($request);
    }

    $credentials = $this->getCredentials($request);
    $logged = Auth::attempt($this->user(), $credentials, $request->has('remember'));

    if ($logged) {
        return $this->handleUserWasAuthenticated($request, $throttles);
    } else {
        return $this->logginLdap($credentials, $request, $throttles);
    }

    // If the login attempt was unsuccessful we will increment the number of attempts
    // to login and redirect the user back to the login form. Of course, when this
    // user surpasses their maximum number of attempts they will get locked out.
    if ($throttles) {
        $this->incrementLoginAttempts($request);
    }

    if ($redirectUrl = $this->loginPath()) {
        return redirect($redirectUrl)
            ->withInput($request->only($this->loginUsername(), 'remember'))
            ->withErrors([
                $this->loginUsername() => $this->getFailedLoginMessage(),
            ]);
    } else {
        return back()
            ->withInput($request->only($this->loginUsername(), 'remember'))
            ->withErrors([
                $this->loginUsername() => $this->getFailedLoginMessage(),
            ]);
    }
}


protected function logginLdap($credenciales, $request, $throttles)
{
    try {
        /// We change from Driver
        $this->user = 'ldap';
        $logged = Auth::attempt($this->user(), $credenciales, $request->has('remember'));

    } catch (\Exception $ex) {
        $logged = false;
    }


    if ($logged) {
        $userLdap = $this->saveUserForDatabase($credenciales);

        if (true === $this->saveUser) {
            /// Close the session generated by ldap:
            Auth::getSession()->remove(\Auth::getName());

            /// Changing Driver to database
            $this->user = 'db';

            /// Create a session for login BD:
            Auth::loginUsingId($userLdap->id);
            Auth::check();

            if ($throttles) {
                $this->clearLoginAttempts($request);
            }

            if (method_exists($this, 'authenticated')) {
                return $this->authenticated(
                    $request,
                    Auth::user($this->user())
                );
            }
        }


        return redirect()->intended($this->redirectPath());

    } else {

        if (true === $this->saveUser) {
            /// changing Driver
            $this->user = 'db';
            if ($throttles) {
                $this->incrementLoginAttempts($request);
            }
        }

        return back()
            ->withInput($request->only($this->loginUsername(), 'remember'))
            ->withErrors([
                $this->loginUsername() => $this->getFailedLoginMessage(),
            ]);


    }
}

``` 
    

Laravel documentation: [Authentication Quickstart](http://laravel.com/docs/master/authentication#authentication-quickstart)


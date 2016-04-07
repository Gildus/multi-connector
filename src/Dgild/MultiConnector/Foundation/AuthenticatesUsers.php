<?php

namespace Dgild\MultiConnector\Foundation;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;

trait AuthenticatesUsers
{

    use AuthenticatesAndRegistersUsers, ThrottlesLogins;

    /**
     * Variable for type of connection DB or LDAP
     *
     * @var null
     */
    protected $user = null;

    /**
     * Variable for indicator if the user logged
     * will be saved if not exists in the database.
     *
     * @var bool
     */
    protected $saveUser = false;

    /**
     * Returns the type of user
     *
     * @return string
     */
    protected function user() {

        $this->checkUser();

        return $this->user;
    }

    /**
     * Checks User has been set or not. If not throw an exception
     * @return null
     */
    public function checkUser() {

        if (!$this->user) {
            throw new \InvalidArgumentException('First parameter should not be empty');
        }

        $app = app();

        if (!array_key_exists($this->user, $app->config['auth.multi'])) {
            throw new \InvalidArgumentException('Undefined property '.$this->user.' not found in auth.php multi array');
        }

    }

    /**
     * Show the application login form.
     *
     * @return \Illuminate\Http\Response
     */
    public function getLogin()
    {

        if (\Auth::check()) {
            return redirect()->intended($this->redirectPath());
        }

        if (view()->exists($this->user().'.authenticate')) {
            return view($this->user().'.authenticate');
        }

        if (view()->exists('auth.login')) {
            return view('auth.login');
        }

        if (view()->exists('auth.authenticate')) {
            return view('auth.authenticate');
        }


        return view($this->user().'.login');
    }

    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
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
    }

    /**
     * Send the response after the user was authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  bool  $throttles
     * @return \Illuminate\Http\Response
     */
    protected function handleUserWasAuthenticated(Request $request, $throttles)
    {
        if ($throttles) {
            $this->clearLoginAttempts($request);
        }

        if (method_exists($this, 'authenticated')) {
            return $this->authenticated($request, Auth::user($this->user()));
        }

        return redirect()->intended($this->redirectPath());
    }

    /**
     * Get the needed authorization credentials from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function getCredentials(Request $request)
    {
        return $request->only($this->loginUsername(), 'password');
    }

    /**
     * Get the failed login message.
     *
     * @return string
     */
    protected function getFailedLoginMessage()
    {
        return Lang::has('auth.failed')
            ? Lang::get('auth.failed')
            : 'These credentials do not match our records.';
    }

    /**
     * Log the user out of the application.
     *
     * @return \Illuminate\Http\Response
     */
    public function getLogout()
    {
        Auth::getSession()->flush();
        Auth::logout($this->user());
        return redirect(property_exists($this, 'redirectAfterLogout') ? $this->redirectAfterLogout : '/');
    }

    /**
     * Get the path to the login route.
     *
     * @return string
     */
    public function loginPath()
    {
        return property_exists($this, 'loginPath') ? $this->loginPath : null;
    }

    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    public function loginUsername()
    {
        return isset($this->username) ? $this->username : 'email';
    }

    /**
     * Determine if the class is using the ThrottlesLogins trait.
     *
     * @return bool
     */
    protected function isUsingThrottlesLoginsTrait()
    {
        return in_array(
            ThrottlesLogins::class, class_uses_recursive(get_class($this))
        );
    }

    /**
     * Logea a LDAP y registra en la BD en caso de no existir
     * para los siguientes logeos sera por BD
     *
     * @param array $credenciales    Usuairo y clave
     * @param Request $request         Request POST HTTP
     * @param boolean $throttles       Not  used for moment
     * @return void
     */
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

    /**
     * Save user for Database if not exists otherwise
     * return the user for database.
     *
     * It is important to maintain the structure of the table user,
     * its necessary the fields:
     * - username
     * - mail
     * - name
     * - first_name
     * - password
     *
     * @param $credenciales     Username or Email with the password
     * @return mixed            Object user for database
     */
    private function saveUserForDatabase($credenciales)
    {
        $name = Auth::user($this->user())->getAuthIdentifier();
        $password = \bcrypt($credenciales['password']);
        $firstName = Auth::user($this->user())->getFirstname();
        $email = Auth::user($this->user())->getEmail();

        /// If the user exists in the BD before to register
        $userLdap = User::where('username', '=', $name)
            ->orWhere('email', $email)
            ->get()
            ->first();

        if (!$userLdap) {
            /// Make insert into BD:
            $userLdap = User::create([
                'name' => $firstName,
                'username' => $name,
                'email' => $email,
                'password' => $password,
            ]);
        } else {
            $userLdap->name = $firstName;
            $userLdap->username = $name;
            $userLdap->email = $email;
            $userLdap->password = $password;
            $userLdap->save();
        }

        return $userLdap;
    }

}

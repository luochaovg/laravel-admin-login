<?php

namespace Encore\James;

use Earnp\GoogleAuthenticator\GoogleAuthenticator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

class JamesController extends Controller
{
    /**
     * Show the login page.
     *
     * @return \Illuminate\Contracts\View\Factory|Redirect|\Illuminate\View\View
     */
    public function getLogin()
    {
        if ($this->guard()->check()) {
            return redirect($this->redirectPath());
        }

        return view('login-captcha::index');
    }

    /**
     * Handle a login request.
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function postLogin(Request $request)
    {
        $credentials = $request->only([$this->username(), 'password', 'captcha']);
        $remember = $request->get('remember', false);

        /** @var \Illuminate\Validation\Validator $validator */
        $validator = Validator::make($credentials, [
            $this->username()   => 'required',
            'password'          => 'required',
            'captcha'           => 'required|captcha',
        ]);

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        unset($credentials['captcha']);

        // google auth
        $googleCheck = $this->googleAuth($request);

        if (Config::get('google.is_switch') && $googleCheck !== true) {
            return back()->withInput()->withErrors($googleCheck);
        }

        if ($this->guard()->attempt($credentials, $remember)) {
            return $this->sendLoginResponse($request);
        }

        return back()->withInput()->withErrors([
            $this->username() => $this->getFailedLoginMessage(),
        ]);
    }

    /**
     * @param $request
     *
     * @return array|bool
     */
    public function googleAuth($request)
    {
        if (empty($request->onecode) || strlen($request->onecode) != 6) {
            return [
                'onecode' => '请正确输入手机上google验证码',
            ];
        };

        $account = $request->get('username');
        $google =  google_secret($account);

        if(GoogleAuthenticator::CheckCode($google, $request->onecode)) {
            return true;
        } else  {
            return [
                'onecode' => '请正确输入手机上google验证码',
            ];
        }
    }

    /**
     * @return string|\Symfony\Component\Translation\TranslatorInterface
     */
    protected function getFailedLoginMessage()
    {
        return Lang::has('auth.failed')
            ? trans('auth.failed')
            : 'These credentials do not match our records.';
    }

    /**
     * Get the post login redirect path.
     *
     * @return string
     */
    protected function redirectPath()
    {
        if (method_exists($this, 'redirectTo')) {
            return $this->redirectTo();
        }

        return property_exists($this, 'redirectTo') ? $this->redirectTo : config('admin.route.prefix');
    }

    /**
     * Send the response after the user was authenticated.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    protected function sendLoginResponse(Request $request)
    {
        admin_toastr(trans('admin.login_successful'));

        $request->session()->regenerate();

        return redirect()->intended($this->redirectPath());
    }

    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    protected function username()
    {
        return 'username';
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard('admin');
    }
}

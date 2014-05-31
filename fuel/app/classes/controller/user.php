<?php

class Controller_User extends Controller_Template
{

	public function action_login()
	{
		$data["subnav"] = array('login'=> 'active' );
		$this->template->title = 'User &raquo; Login';
		$this->template->content = View::forge('user/login', $data);
	}

	public function action_oauth($provider = null)
	{
		// bail out if we don't have an OAuth provider to call
		if ($provider === null)
	    {
			\Log::error(__('login-no-provider-specified'));
			\Response::redirect_back();
		}

		\Auth_Opauth::forge();
	}

	public function action_callback()
	{
		try
		{
			$opauth = \Auth_Opauth::forge(false);
			$status = $opauth->login_or_register();
			$provider = $opauth->get('auth.provider', '?');
			switch ($status)
			{
				case 'linked';
				//\Log::error('[linked]');
				\Session::set_flash('success', sprintf(__('login.provider-linked'), ucfirst($provider)));
				$url = '/';
				break;

				case 'logged_in';
				//\Log::error('[logged_in]');
				\Session::set_flash('success', sprintf(__('login.logged_in_using_provider'), ucfirst($provider)));
				$url = '/';
				break;

				case 'register';
				\Session::set_flash('success', sprintf(__('login.register-first'), ucfirst($provider)));
				$url = 'user/register';
				break;

				case 'registered';
				//\Log::error('[registered]');
				\Session::set_flash('success', __('login.auto-registered'));
				$url = '/';
				break;

				default:
				throw new \FuelException('Auth_Opauth::login_or_register() has come up with a result that we dont know how to handle.');
			}
			\Response::redirect($url);
		}

		catch (\OpauthException $e)
		{
			Log::error($e->getMessage());
			\Response::redirect_back();
		}

		catch (\OpauthCancelException $e)
		{
			exit('It looks like you canceled your authorisation.'.\Html::anchor('users/oath/'.$provider, 'Click here').' to try again.');
		}
	}

	public function action_register()
	{
		// view用のformを作成する
		$form = \Fieldset::forge('registerform');
		$form->form()->add_csrf();
		$form->add_model('Model\\Auth_User');
		//$form->add_after('fullname', '(facebook name)', array(), array(), 'username')->add_rule('required');
		$form->add_after('fullname', __('login.form.fullname'), array(), array(), 'username')->add_rule('required');
		$form->add_after('confirm', __('login.form.confirm'), array('type' => 'password'), array(), 'password')->add_rule('required');
		$form->field('password')->add_rule('required');
		$form->disable('group_id');
		$form->field('group_id')->delete_rule('required')->delete_rule('is_numeric');
		$provider = \Session::get('auth-strategy.authentication.provider', false);

		// was the registration form posted?
		if (\Input::method() == 'POST')
		{
			if ($provider and \Input::post('login'))
			{
				if (\Auth::instance()->login(\Input::param('username'), \Input::param('password')))
				{
					list(, $userid) = \Auth::instance()->get_user_id();
					$this->link_provider($userid);
					\Response::redirect_back('/');
				} 
				else
				{
					Log::error(__('login.failure'));
				}
			}
			elseif(\Input::post('register'))
			{
				// validate the input
				$form->validation()->run();

				//validate the input
				if ( ! $form->validation()->error() )
				{
					try
					{
						$created = \Auth::create_user(
							$form->validated('username'),
							$form->validated('password'),
							$form->validated('email'),
							\Config::get('application.user.default_group', 1),
							array(
								'fullname' => $form->validated('fullname'),
							)
						);

						if ($created)
						{
							$this->link_provider($created);
							\Response::redirect_back('/');
						}
						else
						{
							\Log::error(__('login.account-creation-failed'));
						}
					}
					catch (\SimpleUserUpdateException $e)
					{
						if ($e->getCode() == 2)
						{
							\Log::error(__('login.email-already-exists'));
						}
						elseif ($e->getCode() == 3)
						{
							\Log::error(__('login.username-already-exists'));
						}
						else
						{
							\Log::error($e->getMessage());
						}
					}
				}
			}
			$form->repopulate();
		}
		else
		{
			$user_hash = \Session::get('auth-strategy.user', array());
			$form->populate(array(
				'username' => \Arr::get($user_hash, 'nickname'),
				'fullname' => \Arr::get($user_hash, 'name'),
				'email' => \Arr::get($user_hash, 'email'),
			));
		}
		$form->add('register', '', array('type'=>'hidden', 'value' => '1'));
		$form->add('submit', '', array('type'=>'submit', 'value' => 'submit'));
		return \View::forge('user/registration')->set('form', $form, false)->set('login', isset($login) ? $login : null, false);
	}

	public function action_logout()
	{
		\Auth::dont_remember_me();
		\Auth::logout();
		\Response::redirect_back();
	}

	protected function link_provider($userid)
	{
		if ($authentication = \Session::get('auth-strategy.authentication', array()))
		{
			$opauth = \Auth_Opauth::forge(false);
			$insert_id = $opauth->link_provider(array(
				'parent_id' => $userid,
				'provider' => $authentication['provider'],
				'uid' => $authentication['uid'],
				'access_token' => $authentication['access_token'],
				'secret' => $authentication['secret'],
				'refresh_token' => $authentication['refresh_token'],
				'expires' => $authentication['expires'],
				'created_at' => time(),
        	));
		}
	}
}

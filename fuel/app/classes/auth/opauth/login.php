<?php

class Auth_Opauth_Login extends Auth_Opauth
{
	public static function _init()
	{
		parent::_init();
	}

	public static function is_login_successful()
	{
		if (\Auth::check())
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public static function get_login_users_provider()
	{
		if (static::is_login_successful())
		{
			list(, $user_id) = \Auth::instance()->get_user_id();
			//$user_providers = \DB::select()->from(parent::$provider_table)->where('parent_id', '=', $user_id)->as_object()->execute();
			$user_providers = \DB::select()->from(parent::$provider_table)->where('parent_id', '=', $user_id)->execute();
			return $user_providers->current();
		}
		else
		{
			return array();
		}
	}
}

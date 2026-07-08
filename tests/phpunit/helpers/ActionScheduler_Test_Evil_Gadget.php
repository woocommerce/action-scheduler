<?php

/**
 * A "gadget" class used to prove that deserializing a scheduled action's
 * schedule blob does not instantiate arbitrary classes.
 *
 * If PHP's unserialize() is allowed to instantiate this class, its __wakeup()
 * magic method runs and flips the static flag. A hardened deserialization path
 * must never let that happen.
 */
class ActionScheduler_Test_Evil_Gadget {

	/**
	 * Set to true the moment an instance is woken up by unserialize().
	 *
	 * @var bool
	 */
	public static $fired = false;

	/**
	 * The side effect an attacker's gadget chain would exploit.
	 */
	public function __wakeup() {
		self::$fired = true;
	}
}

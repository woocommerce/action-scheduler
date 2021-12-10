<?php

class ActionScheduler_Pipeline_Exception extends Exception {
	const ONLY_ONE_SERVER_ALLOWED = 100;
	const BAD_ENTITY_TYPE = 101;
	const BAD_STATUS = 102;
	const COULD_NOT_REGISTER_ENTITY = 103;
	const BAD_LOOP_DELAY_METHOD = 104;
	const INCOMPATIBLE_ACTION_STORE = 105;
}

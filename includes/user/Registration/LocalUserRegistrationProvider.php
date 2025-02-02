<?php

namespace MediaWiki\User\Registration;

use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;

class LocalUserRegistrationProvider implements IUserRegistrationProvider {

	public const TYPE = 'local';

	private UserFactory $userFactory;

	public function __construct( UserFactory $userFactory ) {
		$this->userFactory = $userFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function fetchRegistration( UserIdentity $user ) {
		// TODO: Factor this out from User::getRegistration to this method (T352871)
		$user = $this->userFactory->newFromUserIdentity( $user );
		return $user->getRegistration();
	}
}

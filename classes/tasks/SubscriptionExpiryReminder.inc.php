<?php

/**
 * @file classes/tasks/SubscriptionExpiryReminder.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubscriptionExpiryReminder
 * @ingroup tasks
 *
 * @brief Class to perform automated reminders for reviewers.
 */

import('lib.pkp.classes.scheduledTask.ScheduledTask');

class SubscriptionExpiryReminder extends ScheduledTask {

	/**
	 * Constructor.
	 */
	function SubscriptionExpiryReminder() {
		$this->ScheduledTask();
	}

	function sendReminder ($subscription, $journal, $emailKey) {

		$userDao = DAORegistry::getDAO('UserDAO');
		$subscriptionTypeDao = DAORegistry::getDAO('SubscriptionTypeDAO');

		$journalName = $journal->getLocalizedName();
		$user = $userDao->getById($subscription->getUserId());
		if (!isset($user)) return false;

		$subscriptionType = $subscriptionTypeDao->getSubscriptionType($subscription->getTypeId());

		$subscriptionName = $journal->getSetting('subscriptionName');
		$subscriptionEmail = $journal->getSetting('subscriptionEmail');
		$subscriptionPhone = $journal->getSetting('subscriptionPhone');
		$subscriptionFax = $journal->getSetting('subscriptionFax');
		$subscriptionMailingAddress = $journal->getSetting('subscriptionMailingAddress');

		$subscriptionContactSignature = $subscriptionName;

		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER, LOCALE_COMPONENT_APP_COMMON);
		
		if ($subscriptionMailingAddress != '') {
			$subscriptionContactSignature .= "\n" . $subscriptionMailingAddress;
		}
		if ($subscriptionPhone != '') {
			$subscriptionContactSignature .= "\n" . AppLocale::Translate('user.phone') . ': ' . $subscriptionPhone;
		}
		if ($subscriptionFax != '') {
			$subscriptionContactSignature .= "\n" . AppLocale::Translate('user.fax') . ': ' . $subscriptionFax;
		}

		$subscriptionContactSignature .= "\n" . AppLocale::Translate('user.email') . ': ' . $subscriptionEmail;

		$paramArray = array(
			'subscriberName' => $user->getFullName(),
			'journalName' => $journalName,
			'subscriptionType' => $subscriptionType->getSummaryString(),
			'expiryDate' => $subscription->getDateEnd(),
			'username' => $user->getUsername(),
			'subscriptionContactSignature' => $subscriptionContactSignature 
		);

		import('lib.pkp.classes.mail.MailTemplate');
		$mail = new MailTemplate($emailKey, $journal->getPrimaryLocale());
		$mail->setReplyTo($subscriptionEmail, $subscriptionName);
		$mail->addRecipient($user->getEmail(), $user->getFullName());
		$mail->setSubject($mail->getSubject($journal->getPrimaryLocale()));
		$mail->setBody($mail->getBody($journal->getPrimaryLocale()));
		$mail->assignParams($paramArray);
		$mail->send();
	}

	function sendJournalReminders ($journal, $curDate) {

		// Only send reminders if subscriptions are enabled
		if ($journal->getSetting('publishingMode') == PUBLISHING_MODE_SUBSCRIPTION) {

			$curYear = $curDate['year'];
			$curMonth = $curDate['month'];
			$curDay = $curDate['day'];

			// Check if expiry notification before months is enabled
			if ($journal->getSetting('enableSubscriptionExpiryReminderBeforeMonths')) {

				$beforeMonths = $journal->getSetting('numMonthsBeforeSubscriptionExpiryReminder');
				$beforeYears = (int)floor($beforeMonths/12);
				$beforeMonths = (int)fmod($beforeMonths,12);

				$expiryYear = $curYear + $beforeYears + (int)floor(($curMonth+$beforeMonths)/12);
				$expiryMonth = (int)fmod($curMonth+$beforeMonths,12);
				$expiryDay = $curDay;

				// Retrieve all subscriptions that match expiry date
				$individualSubscriptionDao = DAORegistry::getDAO('IndividualSubscriptionDAO');
				$institutionalSubscriptionDao = DAORegistry::getDAO('InstitutionalSubscriptionDAO');
				$dateEnd = $expiryYear . '-' . $expiryMonth . '-' . $expiryDay;
				$individualSubscriptions = $individualSubscriptionDao->getSubscriptionsByDateEnd($dateEnd, $journal->getId()); 
				$institutionalSubscriptions = $institutionalSubscriptionDao->getSubscriptionsByDateEnd($dateEnd, $journal->getId()); 

				while ($subscription = $individualSubscriptions->next()) {
					$this->sendReminder($subscription, $journal, 'SUBSCRIPTION_BEFORE_EXPIRY');
				}

				while ($subscription = $institutionalSubscriptions->next()) {
					$this->sendReminder($subscription, $journal, 'SUBSCRIPTION_BEFORE_EXPIRY');
				}
			}

			// Check if expiry notification before weeks is enabled
			if ($journal->getSetting('enableSubscriptionExpiryReminderBeforeWeeks')) {

				$beforeWeeks = $journal->getSetting('numWeeksBeforeSubscriptionExpiryReminder');
				$beforeDays = $beforeWeeks * 7;

				$expiryMonth = $curMonth + (int)floor(($curDay+$beforeDays)/31);
				$expiryYear = $curYear + (int)floor($expiryMonth/12);
				$expiryDay = (int)fmod($curDay+$beforeDays,31);
				$expiryMonth = (int)fmod($expiryMonth,12);				

				// Retrieve all subscriptions that match expiry date
				$individualSubscriptionDao = DAORegistry::getDAO('IndividualSubscriptionDAO');
				$institutionalSubscriptionDao = DAORegistry::getDAO('InstitutionalSubscriptionDAO');
				$dateEnd = $expiryYear . '-' . $expiryMonth . '-' . $expiryDay;
				$individualSubscriptions = $individualSubscriptionDao->getSubscriptionsByDateEnd($dateEnd, $journal->getId()); 
				$institutionalSubscriptions = $institutionalSubscriptionDao->getSubscriptionsByDateEnd($dateEnd, $journal->getId()); 

				while ($subscription = $individualSubscriptions->next()) {
					$this->sendReminder($subscription, $journal, 'SUBSCRIPTION_BEFORE_EXPIRY');
				}

				while ($subscription = $institutionalSubscriptions->next()) {
					$this->sendReminder($subscription, $journal, 'SUBSCRIPTION_BEFORE_EXPIRY');
				}
			}

			// Check if expiry notification after months is enabled
			if ($journal->getSetting('enableSubscriptionExpiryReminderAfterMonths')) {

				$afterMonths = $journal->getSetting('numMonthsAfterSubscriptionExpiryReminder');
				$afterYears = (int)floor($afterMonths/12);
				$afterMonths = (int)fmod($afterMonths,12);

				if (($curMonth - $afterMonths) <= 0) {
					$afterYears++;
					$expiryMonth = 12 + ($curMonth - $afterMonths);
				} else {
					$expiryMonth = $curMonth - $afterMonths;
				}

				$expiryYear = $curYear - $afterYears;
				$expiryDay = $curDay;

				// Retrieve all subscriptions that match expiry date
				$individualSubscriptionDao = DAORegistry::getDAO('IndividualSubscriptionDAO');
				$institutionalSubscriptionDao = DAORegistry::getDAO('InstitutionalSubscriptionDAO');
				$dateEnd = $expiryYear . '-' . $expiryMonth . '-' . $expiryDay;
				$individualSubscriptions = $individualSubscriptionDao->getSubscriptionsByDateEnd($dateEnd, $journal->getId()); 
				$institutionalSubscriptions = $institutionalSubscriptionDao->getSubscriptionsByDateEnd($dateEnd, $journal->getId()); 

				while ($subscription = $individualSubscriptions->next()) {
					$this->sendReminder($subscription, $journal, 'SUBSCRIPTION_AFTER_EXPIRY_LAST');
				}

				while ($subscription = $institutionalSubscriptions->next()) {
					$this->sendReminder($subscription, $journal, 'SUBSCRIPTION_AFTER_EXPIRY_LAST');
				}
			}

			// Check if expiry notification after weeks is enabled
			if ($journal->getSetting('enableSubscriptionExpiryReminderAfterWeeks')) {

				$afterWeeks = $journal->getSetting('numWeeksAfterSubscriptionExpiryReminder');
				$afterDays = $afterWeeks * 7;

				if (($curDay - $afterDays) <= 0) {
					$afterMonths = 1;
					$expiryDay = 31 + ($curDay - $afterDays);
				} else {
					$afterMonths = 0;
					$expiryDay = $curDay - $afterDays;
				}

				if (($curMonth - $afterMonths) == 0) {
					$afterYears = 1;
					$expiryMonth = 12;
				} else {
					$afterYears = 0;
					$expiryMonth = $curMonth - $afterMonths;
				}

				$expiryYear = $curYear - $afterYears;

				// Retrieve all subscriptions that match expiry date
				$individualSubscriptionDao = DAORegistry::getDAO('IndividualSubscriptionDAO');
				$institutionalSubscriptionDao = DAORegistry::getDAO('InstitutionalSubscriptionDAO');
				$dateEnd = $expiryYear . '-' . $expiryMonth . '-' . $expiryDay;
				$individualSubscriptions = $individualSubscriptionDao->getSubscriptionsByDateEnd($dateEnd, $journal->getId()); 
				$institutionalSubscriptions = $institutionalSubscriptionDao->getSubscriptionsByDateEnd($dateEnd, $journal->getId()); 

				while ($subscription = $individualSubscriptions->next()) {
					$this->sendReminder($subscription, $journal, 'SUBSCRIPTION_AFTER_EXPIRY');
				}

				while ($subscription = $institutionalSubscriptions->next()) {
					$this->sendReminder($subscription, $journal, 'SUBSCRIPTION_AFTER_EXPIRY');
				}
			}
		}
	}

	function execute() {
		$journalDao = DAORegistry::getDAO('JournalDAO');
		$journals = $journalDao->getAll(true);

		$todayDate = array(
			'year' => date('Y'),
			'month' => date('n'),
			'day' => date('j')
		);

		while ($journal = $journals->next()) {
			// Send reminders based on current date
			$this->sendJournalReminders($journal, $todayDate);
		}

		// If it is the first day of a month but previous month had only
		// 30 days then simulate 31st day for expiry dates that end on
		// that day.
		$shortMonths = array(2,4,6,8,10,12);

		if (($todayDate['day'] == 1) && in_array(($todayDate['month'] - 1), $shortMonths)) {

			$curDate['day'] = 31;
			$curDate['month'] = $todayDate['month'] - 1;

			if ($curDate['month'] == 12) {
				$curDate['year'] = $todayDate['year'] - 1;
			} else {
				$curDate['year'] = $todayDate['year'];
			}

			$journals = $journalDao->getAll(true);

			while ($journal = $journals->next()) {
				// Send reminders for simulated 31st day of short month
				$this->sendJournalReminders($journal, $curDate);
			}
		}

		// If it is the first day of March, simulate 29th and 30th days for February
		// or just the 30th day in a leap year.
		if (($todayDate['day'] == 1) && ($todayDate['month'] == 3)) {

			$curDate['day'] = 30;
			$curDate['month'] = 2;
			$curDate['year'] = $todayDate['year'];

			$journals = $journalDao->getAll(true);

			while ($journal = $journals->next()) {
				// Send reminders for simulated 30th day of February
				$this->sendJournalReminders($journal, $curDate);
			}

			// Check if it's a leap year
			if (date("L", mktime(0,0,0,0,0,$curDate['year'])) != '1') {

				$curDate['day'] = 29;

				$journals = $journalDao->getAll(true);

				while ($journal = $journals->next()) {
					// Send reminders for simulated 29th day of February
					$this->sendJournalReminders($journal, $curDate);
				}
			}
		}
	}
}

?>

<?php 
namespace vaterlangen\CalDavBundle\Model;

interface EventReminderInterface extends EventInterface
{
	/**
	 * Get the reminder trigger time
	 *
	 * @return string Reminder start time
	 */
	public function getReminderTrigger();

    /**
	 * Get the reminder message
	 *
	 * @return string Reminder message
	 */
	public function getReminderMessage();

    /**
	 * Get repeat count
	 *
	 * @return integer Reminder repeats
	 */
	public function getReminderRepeatCount();

    /**
	 * Get repeat delay
	 *
	 * @return string Reminder delays
	 */
	public function getReminderRepeatDelay();
}

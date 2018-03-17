<?php 
namespace vaterlangen\CalDavBundle\Model;

interface EventInterface extends CalDavInterface
{
	/**
	 * Get the summary
	 *
	 * @return string Summary
	 */
	public function getEventSummary();

    /**
	 * Get the description
	 *
	 * @return string Description
	 */
	public function getEventDescription();

	/**
	 * Get the begin
	 *
	 * @return DateTime Start of Event
	 */
	public function getEventBegin();
	
	/**
	 * Get the end
	 *
	 * @return DateTime End of Event
	 */
	public function getEventEnd();

}

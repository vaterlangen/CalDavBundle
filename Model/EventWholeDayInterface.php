<?php 
namespace vaterlangen\CalDavBundle\Model;

interface EventWholeDayInterface extends CalDavInterface
{
	/**
	 * Is event for the whole day?
	 * 
	 * @return boolean
	 */
	public function isEventWholeDay();
}

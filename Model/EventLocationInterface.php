<?php 
namespace vaterlangen\CalDavBundle\Model;

interface EventLocationInterface extends CalDavInterface
{
	/**
	 * Get the location
	 * 
	 * @return string Location
	 */
	public function getEventLocation();
}

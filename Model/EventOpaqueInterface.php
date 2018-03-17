<?php 
namespace vaterlangen\CalDavBundle\Model;

interface EventOpaqueInterface extends CalDavInterface
{
	/**
	 * Wether the event is rendered as opaque or not
	 * 
	 * @return boolean
	 */
	public function isEventOpaque();
}

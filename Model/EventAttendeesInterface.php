<?php 
namespace vaterlangen\CalDavBundle\Model;

interface EventAttendeesInterface extends EventInterface
{
	public function getEventAttendees();
}

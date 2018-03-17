<?php 
namespace vaterlangen\CalDavBundle\Model;

interface AttendeeInterface
{
	public function getFullName();
    public function getMail();
    public function isRequired();
}

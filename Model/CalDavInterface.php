<?php 
namespace vaterlangen\CalDavBundle\Model;

interface CalDavInterface
{
	/**
	 * Get the unique identifier
	 *
	 * @return string ID
	 */
	public function getCalDavID();
	
	
	/**
	 * Set the unique identifier
	 *
	 * @param string $id
	 */
	public function setCalDavID($id);

    /**
	 * get creation date
     *
     * @return DateTime Creation date
	 */
	public function getCreatedAt();	
	
	/**
	 * Set the categories
	 *
	 * @return array Categories to set
	 */
	public function getEventCategories();
}

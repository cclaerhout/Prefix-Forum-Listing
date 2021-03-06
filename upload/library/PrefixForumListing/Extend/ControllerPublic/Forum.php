<?php

class PrefixForumListing_Extend_ControllerPublic_Forum extends XFCP_PrefixForumListing_Extend_ControllerPublic_Forum
{
	public function actionForum()
	{
		$response = parent::actionForum();

		if ($this->_routeMatch->getResponseType() == 'rss')
		{
			return $response;
		}

		/* Get the user browsing this page */
		$viewingUser = XenForo_Visitor::getInstance()->toArray();

		/* Get the options */
		$options = XenForo_Application::get('options');

		/* Get the forum data */
		$forumId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$forumName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);

		if(!$forumId && !$forumName)
		{
			return $response;
		}

		$forum = $this->getHelper('ForumThreadPost')->assertForumValidAndViewable(
			$forumId ? $forumId : $forumName,
			$this->_getForumFetchOptions()
		);

		$forumId = $forum['node_id'];

		if (!in_array($forumId, $options->pfl_display_in_forums) && !in_array($forum['parent_node_id'], $options->pfl_display_in_forums))
		{
			return $response;
		}

		$prefixModel = $this->_getThreadPrefixModel();
		$prefixesIds = array();

		$cache = false;

		/* Try to get the total thread count of each prefix in the cache */
		$totalThreadsCache = $this->_getDataRegistryModel()->get('PrefixesThreadsCount');

		// Get all prefixes of this forum from cache
		$prefixIdCache = $forum['prefixCache'];

		/* If this forum has any prefix*/
		if (!$prefixIdCache)
		{
			return $response;
		}	
			
		// Lets get all id's
		foreach ($prefixIdCache as $key => $prefixes)
		{
			foreach ($prefixes as $key => $prefix)
			{
				$prefixesIds[] = $prefix;
			}
		}

		// if the user wants to limit the ammount of prefix...lets do it!
		$limit = array();

		if ($options->pfl_ammount > 0)
		{
			$limit = array('limit' => $options->pfl_ammount);
		}

		// Get all prefixes in one time only (1 query)
		$prefixes = $prefixModel->getPrefixes(array('prefix_ids' => $prefixesIds), $limit);

		foreach ($prefixes as $key => &$prefix)
		{
			// verify if the current browsing user can see this prefix
			$userGroups = explode(',', $prefix['allowed_user_group_ids']);
			if (in_array(-1, $userGroups) || in_array($viewingUser['user_group_id'], $userGroups))
			{
				// available to all groups or the primary group
			}
			elseif ($viewingUser['secondary_group_ids'])
			{
				foreach (explode(',', $viewingUser['secondary_group_ids']) AS $userGroupId)
				{
					if (!in_array($userGroupId, $userGroups))
					{
						// Do not have permission to see this prefix
						unset($prefixes[$key]);
					}
				}
			}

			/* If there is nothing (threads count) in the cache for this prefix */
			if (!isset($totalThreadsCache[$forumId][$prefix['prefix_id']]))
			{
				$cache = true;

				/* Count threads*/
				$threadFetchOptions = array(
					'prefix_id' => $prefix['prefix_id'],
					'deleted' =>  false,
					'moderated' => false
				);
				$totalThreads = $this->_getThreadModel()->countThreadsInForum($forumId, $threadFetchOptions);

				/* Cache total threads */
				$totalThreadsCache[$forumId][$prefix['prefix_id']] = $totalThreads;

				/* Verify if the user wants to show all threads or only with some ammount */
				if ($options->pfl_donotshow_totalthreads > 0)
				{
					/* If the total threads of this prefix is < of the option set, we dont want to show it */
					if ($totalThreads < $options->pfl_donotshow_totalthreads)
					{
						unset($prefixes[$key]);
					}
				}

				/* Set the total threads */
				$prefix['totalThreads'] = $totalThreads;
			}
			else
			{
				/* Verify if the user wants to show all threads or only with some ammount */
				if ($options->pfl_donotshow_totalthreads > 0)
				{
					/* If the total threads (cache) of this prefix is < of the option set, we dont want to show it */
					if ($totalThreadsCache[$forumId][$prefix['prefix_id']] < $options->pfl_donotshow_totalthreads)
					{
						unset($prefixes[$key]);
					}
				}

				$prefix['totalThreads'] = $totalThreadsCache[$forumId][$prefix['prefix_id']];
			}

			$prefix = $this->_getThreadPrefixModel()->preparePrefix($prefix);

			if(isset($prefix['title']))
			{
				$prefix['title'] = $prefix['title']->render();
			}
						
			$totalThreads = 0;
		}

		/* Update the cache! */
		if ($cache)
		{
			$this->_getDataRegistryModel()->set('PrefixesThreadsCount', $totalThreadsCache);
		}

		//Sort the prefixes
		$this->_getPrefixListingModel()->multi_sort($prefixes, $options->pfl_display_order, $options->pfl_orderDirection);
		$response->params['prefixes'] = $prefixes;

		return $response;
	}

	/**
	 * @return PrefixForumListing_Model_PrefixListing
	 */
	protected function _getPrefixListingModel()
	{
		return $this->getModelFromCache('PrefixForumListing_Model_PrefixListing');
	}

	/**
	 * @return XenForo_Model_ThreadPrefix
	 */
	protected function _getThreadPrefixModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadPrefix');
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}

	/**
	 * @return XenForo_Model_DataRegistry
	 */
	protected function _getDataRegistryModel()
	{
		return $this->getModelFromCache('XenForo_Model_DataRegistry');
	}
}
//Zend_Debug::dump($abc);
<?php
/**
 * Kunena Component
 *
 * @package         Kunena.Administrator
 * @subpackage      Models
 *
 * @copyright       Copyright (C) 2008 - 2021 Kunena Team. All rights reserved.
 * @license         https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link            https://www.kunena.org
 **/

namespace Kunena\Forum\Administrator\Model;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Kunena\Forum\Libraries\Access\KunenaAccess;
use Kunena\Forum\Libraries\Forum\Category\KunenaCategory;
use Kunena\Forum\Libraries\Forum\Category\KunenaCategoryHelper;
use Kunena\Forum\Libraries\Forum\Topic\KunenaTopic;
use Kunena\Forum\Libraries\Forum\Topic\KunenaTopicHelper;
use Kunena\Forum\Libraries\Model\KunenaModel;
use Kunena\Forum\Libraries\User\KunenaUser;
use Kunena\Forum\Libraries\User\KunenaUserHelper;
use RuntimeException;
use function defined;

/**
 * User Model for Kunena
 *
 * @since  3.0
 */
class UserModel extends KunenaModel
{

	/**
	 * @param   array    $data      data
	 * @param   boolean  $loadData  load data
	 *
	 * @return void
	 *
	 * @since  Kunena 6.0
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// TODO: Implement getForm() method.
	}

	/**
	 * @return  array|KunenaTopic[]|boolean
	 *
	 * @since   Kunena 6.0
	 *
	 * @throws  Exception
	 * @throws  null
	 */
	public function getSubscriptions(): array
	{
		$db     = Factory::getDBO();
		$userid = $this->getState($this->getName() . '.id');

		$query = $db->getQuery(true);
		$query->select('topic_id AS thread')
			->from($db->quoteName('#__kunena_user_topics'))
			->where('user_id = ' . $userid . ' AND subscribed=1');
		$db->setQuery($query);

		try
		{
			$subsList = (array) $db->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			Factory::getApplication()->enqueueMessage($e->getMessage());

			return false;
		}

		$topicList = [];

		if (!empty($subsList))
		{
			foreach ($subsList as $sub)
			{
				$topicList[] = $sub->thread;
			}

			$topicList = KunenaTopicHelper::getTopics($topicList);
		}

		return $topicList;
	}

	/**
	 * @return  KunenaCategory[]
	 *
	 * @since   Kunena 6.0
	 *
	 * @throws  Exception
	 */
	public function getCatSubscriptions(): array
	{
		$userid = $this->getState($this->getName() . '.id');

		return KunenaCategoryHelper::getSubscriptions($userid);
	}

	/**
	 * @return  array|boolean
	 *
	 * @since   Kunena 6.0
	 *
	 * @throws  Exception
	 */
	public function getIPlist(): array
	{
		$db     = Factory::getDBO();
		$userid = $this->getState($this->getName() . '.id');

		$query = $db->getQuery(true);
		$query->select('ip')
			->from($db->quoteName('#__kunena_messages'))
			->where('userid = ' . $userid)
			->group('ip');
		$db->setQuery($query);

		try
		{
			$ipList = implode("','", (array) $db->loadColumn());
		}
		catch (RuntimeException $e)
		{
			Factory::getApplication()->enqueueMessage($e->getMessage());

			return false;
		}

		$list = [];

		if ($ipList)
		{
			$ipList = "'{$ipList}'";
			$query  = $db->getQuery(true);
			$query->select('m.ip,m.userid,u.username,COUNT(*) as mescnt')
				->from($db->quoteName('#__kunena_messages', 'm'))
				->innerJoin($db->quoteName('#__users', 'u') . ' ON m.userid = u.id')
				->where('m.ip IN (' . $ipList . ')')
				->group('m.userid,m.ip');
			$db->setQuery($query);

			try
			{
				$list = (array) $db->loadObjectlist();
			}
			catch (RuntimeException $e)
			{
				Factory::getApplication()->enqueueMessage($e->getMessage());

				return false;
			}
		}

		$userIpsList = [];

		foreach ($list as $item)
		{
			$userIpsList[$item->ip][] = $item;
		}

		return $userIpsList;
	}

	/**
	 * @return  mixed
	 *
	 * @since   Kunena 6.0
	 *
	 * @throws  Exception
	 */
	public function getListModCats()
	{
		$user = $this->getUser();

		$modCatList = array_keys(KunenaAccess::getInstance()->getModeratorStatus($user));

		if (empty($modCatList))
		{
			$modCatList[] = 0;
		}

		$categoryList = [];

		if ($this->me->isAdmin())
		{
			$categoryList[] = HTMLHelper::_('select.option', 0, Text::_('COM_KUNENA_GLOBAL_MODERATOR'));
		}

		// Todo: fix params
		$params = [
			'sections' => false,
			'action'   => 'read',];

		return HTMLHelper::_('select.genericlist', $categoryList, 'catid', 'class="inputbox form-control" multiple="multiple" size="15"', 'value', 'text');
	}

	/**
	 * @return  KunenaUser
	 *
	 * @since   Kunena 6.0
	 *
	 * @throws  Exception
	 */
	public function getUser(): KunenaUser
	{
		$userid = $this->getState($this->getName() . '.id');

		return KunenaUserHelper::get($userid);
	}

	/**
	 * @return  array|mixed|void
	 *
	 * @since   Kunena 6.0
	 *
	 * @throws  Exception
	 */
	public function getListUserRanks(): array
	{
		$db   = Factory::getDBO();
		$user = $this->getUser();

		// Grab all special ranks
		$query = $db->getQuery(true);
		$query->select('*')
			->from($db->quoteName('#__kunena_ranks'))
			->where('rankSpecial = \'1\'');
		$db->setQuery($query);

		try
		{
			$specialRanks = (array) $db->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			Factory::getApplication()->enqueueMessage($e->getMessage());

			return false;
		}

		$yesnoRank [] = HTMLHelper::_('select.option', '0', Text::_('COM_KUNENA_RANK_NO_ASSIGNED'));

		foreach ($specialRanks as $ranks)
		{
			$yesnoRank [] = HTMLHelper::_('select.option', $ranks->rankId, $ranks->rankTitle);
		}

		// Build special ranks select list
		return HTMLHelper::_('select.genericlist', $yesnoRank, 'newRank', 'class="inputbox form-control" size="5"', 'value', 'text', $user->rank);
	}

	/**
	 * @return  mixed
	 *
	 * @since   Kunena 6.0
	 */
	public function getMoveCatsList()
	{
		return HTMLHelper::_('select.genericlist', '', 'catid', 'class="inputbox form-control"', 'value', 'text');
	}

	/**
	 * @return  array|void|null
	 *
	 * @since   Kunena 6.0
	 *
	 * @throws  Exception
	 */
	public function getMoveUser()
	{
		$db = Factory::getDBO();

		$userids = (array) $this->app->getUserState('kunena.usermove.userids');

		if (!$userids)
		{
			return $userids;
		}

		$userids = implode(',', $userids);
		$query   = $db->getQuery(true);
		$query->select('id,username')
			->from($db->quoteName('#__users'))
			->where('id IN(' . $userids . ')');
		$db->setQuery($query);

		try
		{
			$userids = (array) $db->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			Factory::getApplication()->enqueueMessage($e->getMessage());

			return;
		}

		return $userids;
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * @param   null  $ordering   ordering
	 * @param   null  $direction  direction
	 *
	 * @return  void
	 *
	 * @since   Kunena 6.0
	 *
	 * @throws Exception
	 */
	protected function populateState($ordering = null, $direction = null): void
	{
		$context = 'com_kunena.admin.user';

		$app = Factory::getApplication();

		// Adjust the context to support modal layouts.
		$layout  = $app->input->get('layout');
		$context = 'com_kunena.admin.user';

		if ($layout)
		{
			$context .= '.' . $layout;
		}

		$value = Factory::getApplication()->input->getInt('userid');
		$this->setState($this->getName() . '.id', $value);
	}
}

<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\documentation\notification\type;

/**
 * Singleton system-status notification: sent to founder-level admins when
 * no documentation build is found at the configured path, and cleared
 * (deleted) the next time a build is found present.
 */
class no_docs_build extends \phpbb\notification\type\base
{
	/** @var \phpbb\config\config */
	protected $config;

	/**
	 * @param \phpbb\config\config $config
	 * @return void
	 */
	public function set_config(\phpbb\config\config $config)
	{
		$this->config = $config;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_type()
	{
		return 'notification.type.phpbbmodders_documentation_no_build';
	}

	/**
	 * {@inheritdoc}
	 */
	protected $language_key = 'NOTIFICATION_DOCUMENTATION_NO_BUILD';

	/**
	 * {@inheritdoc}
	 */
	public static $notification_option = array(
		'lang'  => 'NOTIFICATION_TYPE_DOCUMENTATION_NO_BUILD',
		'group' => 'NOTIFICATION_GROUP_ADMINISTRATION',
	);

	/**
	 * {@inheritdoc}
	 */
	public function is_available()
	{
		return true;
	}

	/**
	 * There's only ever one instance of this condition, so the item id is
	 * a fixed constant rather than tied to a database row.
	 *
	 * {@inheritdoc}
	 */
	public static function get_item_id($type_data)
	{
		return 1;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_item_parent_id($type_data)
	{
		return 0;
	}

	/**
	 * Recipients default to founder-level admins only. If
	 * phpbbmodders_documentation_notify_recipients is set to
	 * 'founders_admins', anyone holding the a_board permission is
	 * notified too — the same permission that gates this extension's own
	 * ACP settings page, so it's exactly the set of admins who could
	 * actually fix a missing build.
	 *
	 * {@inheritdoc}
	 */
	public function find_users_for_notification($type_data, $options = array())
	{
		$options = array_merge(array(
			'ignore_users' => array(),
		), $options);

		$sql = 'SELECT user_id
			FROM ' . USERS_TABLE . '
			WHERE user_type = ' . USER_FOUNDER;
		$result = $this->db->sql_query($sql);

		$users = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$users[] = (int) $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		if ($this->config !== null && $this->config['phpbbmodders_documentation_notify_recipients'] === 'founders_admins')
		{
			$admin_ary = $this->auth->acl_get_list(false, 'a_board', false);
			if (!empty($admin_ary[0]['a_board']))
			{
				$users = array_merge($users, $admin_ary[0]['a_board']);
			}
		}

		$users = array_unique($users);

		if (empty($users))
		{
			return array();
		}

		return $this->check_user_notification_options($users, $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function users_to_query()
	{
		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_avatar()
	{
		return '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_title()
	{
		return $this->language->lang($this->language_key);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_url()
	{
		return append_sid("{$this->phpbb_root_path}adm/index.{$this->php_ext}", 'i=-phpbbmodders-documentation-acp-main_module&amp;mode=settings');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_email_template()
	{
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_email_template_variables()
	{
		return array();
	}
}

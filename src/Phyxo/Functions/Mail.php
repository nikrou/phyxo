<?php
/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phyxo\Functions;

use Pelago\Emogrifier;

class Mail
{
    /**
     * Returns the name of the mail sender
     *
     * @return string
     */
    public static function get_mail_sender_name()
    {
        global $conf;

        return (empty($conf['mail_sender_name']) ? $conf['gallery_title'] : $conf['mail_sender_name']);
    }

    /**
     * Returns the email of the mail sender
     *
     * @return string
     */
    public static function get_mail_sender_email()
    {
        global $conf;

        return (empty($conf['mail_sender_email']) ? \Phyxo\Functions\Utils::get_webmaster_mail_address() : $conf['mail_sender_email']);
    }

    /**
     * Returns an array of mail configuration parameters.
     * - send_bcc_mail_webmaster
     * - mail_allow_html
     * - use_smtp
     * - smtp_host
     * - smtp_user
     * - smtp_password
     * - smtp_secure
     * - email_webmaster
     * - name_webmaster
     *
     * @return array
     */
    public static function get_mail_configuration()
    {
        global $conf;

        $conf_mail = [
            'send_bcc_mail_webmaster' => $conf['send_bcc_mail_webmaster'],
            'mail_allow_html' => $conf['mail_allow_html'],
            'mail_theme' => $conf['mail_theme'],
            'use_smtp' => !empty($conf['smtp_host']),
            'smtp_host' => $conf['smtp_host'],
            'smtp_user' => $conf['smtp_user'],
            'smtp_password' => $conf['smtp_password'],
            'smtp_secure' => $conf['smtp_secure'],
            'email_webmaster' => self::get_mail_sender_email(),
            'name_webmaster' => self::get_mail_sender_name(),
        ];

        return $conf_mail;
    }

    /**
     * Returns an email address with an associated real name.
     * Can return either:
     *    - email@domain.com
     *    - name <email@domain.com>
     *
     * @param string $name
     * @param string $email
     * @return string
     */
    public static function format_email($name, $email)
    {
        $cvt_email = trim(preg_replace('#[\n\r]+#s', '', $email));
        $cvt_name = trim(preg_replace('#[\n\r]+#s', '', $name));

        if ($cvt_name != "") {
            $cvt_name = '"' . addcslashes($cvt_name, '"') . '"' . ' ';
        }

        if (strpos($cvt_email, '<') === false) {
            return $cvt_name . '<' . $cvt_email . '>';
        } else {
            return $cvt_name . $cvt_email;
        }
    }

    /**
     * Returns the email and the name from a formatted address.
     *
     * @param string|string[] $input - if is an array must contain email[, name]
     * @return array email, name
     */
    public static function unformat_email($input)
    {
        if (is_array($input)) {
            if (!isset($input['name'])) {
                $input['name'] = '';
            }
            return $input;
        }

        if (preg_match('/(.*)<(.*)>.*/', $input, $matches)) {
            return [
                'email' => trim($matches[2]),
                'name' => trim($matches[1]),
            ];
        } else {
            return [
                'email' => trim($input),
                'name' => '',
            ];
        }
    }

    /**
     * Return a clean array of hashmaps (email, name) removing duplicates.
     * It accepts various inputs:
     *    - comma separated list
     *    - array of emails
     *    - single hashmap (email[, name])
     *    - array of incomplete hashmaps
     *
     * @param mixed $data
     * @return string[][]
     */
    public static function get_clean_recipients_list($data)
    {
        if (empty($data)) {
            return [];
        } elseif (is_array($data)) {
            $values = array_values($data);
            if (!is_array($values[0])) {
                $keys = array_keys($data);
                if (is_int($keys[0])) { // simple array of emails
                    foreach ($data as &$item) {
                        $item = [
                            'email' => trim($item),
                            'name' => '',
                        ];
                    }
                    unset($item);
                } else { // hashmap of one recipient
                    $data = [self::unformat_email($data)];
                }
            } else { // array of hashmaps
                $data = array_map('self::unformat_email', $data);
            }
        } else {
            $data = explode(',', $data);
            $data = array_map('self::unformat_email', $data);
        }

        $existing = [];
        foreach ($data as $i => $entry) {
            if (isset($existing[$entry['email']])) {
                unset($data[$i]);
            } else {
                $existing[$entry['email']] = true;
            }
        }

        return array_values($data);
    }

    /**
     * Return an new mail template.
     *
     * @param string $email_format - text/html or text/plain
     * @return Template
     */
    public static function get_mail_template($email_format)
    {
        global $conf, $lang, $lang_info;

        $template = new \Phyxo\Template\Template(['conf' => $conf, 'lang' => $lang, 'lang_info' => $lang_info]);
        $template->set_theme(PHPWG_ROOT_PATH . 'themes', 'default', 'template/mail/' . $email_format);

        return $template;
    }

    /**
     * Return string email format (text/html or text/plain).
     *
     * @param bool $is_html
     * @return string
     */
    public static function get_str_email_format($is_html)
    {
        return ($is_html ? 'text/html' : 'text/plain');
    }

    /**
     * Switch language to specified language.
     * All entries are push on language stack
     *
     * @param string $language
     */
    public static function switch_lang_to($language)
    {
        global $switch_lang, $user, $lang, $lang_info, $language_files;

        // explanation of switch_lang
        // $switch_lang['language'] contains data of language
        // $switch_lang['stack'] contains stack LIFO
        // $switch_lang['initialisation'] allow to know if it's first call

        // Treatment with current user
        // Language of current user is saved (it's considered OK on firt call)
        if (!isset($switch_lang['initialisation']) and !isset($switch_lang['language'][$user['language']])) {
            $switch_lang['initialisation'] = true;
            $switch_lang['language'][$user['language']]['lang_info'] = $lang_info;
            $switch_lang['language'][$user['language']]['lang'] = $lang;
        }

        // Change current infos
        $switch_lang['stack'][] = $user['language'];
        $user['language'] = $language;

        // Load new data if necessary
        if (!isset($switch_lang['language'][$language])) {
            // Re-Init language arrays
            $lang_info = [];
            $lang = [];

            // language files
            \Phyxo\Functions\Language::load_language('common.lang', '', ['language' => $language]);
            // No test admin because script is checked admin (user selected no)
            // Translations are in admin file too
            \Phyxo\Functions\Language::load_language('admin.lang', '', ['language' => $language]);

            // Reload all plugins files (see \Phyxo\Functions\Language::load_language declaration)
            if (!empty($language_files)) {
                foreach ($language_files as $dirname => $files) {
                    foreach ($files as $filename => $options) {
                        $options['language'] = $language;
                        \Phyxo\Functions\Language::load_language($filename, $dirname, $options);
                    }
                }
            }

            \Phyxo\Functions\Plugin::trigger_notify('loading_lang');
            \Phyxo\Functions\Language::load_language(
                'lang',
                PHPWG_ROOT_PATH . PWG_LOCAL_DIR,
                ['language' => $language, 'no_fallback' => true, 'local' => true]
            );

            $switch_lang['language'][$language]['lang_info'] = $lang_info;
            $switch_lang['language'][$language]['lang'] = $lang;
        } else {
            $lang_info = $switch_lang['language'][$language]['lang_info'];
            $lang = $switch_lang['language'][$language]['lang'];
        }
    }

    /**
     * Switch back language pushed with switch_lang_to() function.
     * @see switch_lang_to()
     * Language files are not reloaded
     */
    public static function switch_lang_back()
    {
        global $switch_lang, $user, $lang, $lang_info;

        if (count($switch_lang['stack']) > 0) {
            // Get last value
            $language = array_pop($switch_lang['stack']);

            // Change current infos
            if (isset($switch_lang['language'][$language])) {
                $lang_info = $switch_lang['language'][$language]['lang_info'];
                $lang = $switch_lang['language'][$language]['lang'];
            }
            $user['language'] = $language;
        }
    }

    /**
     * Send a notification email to all administrators.
     * current user (if admin) is not notified
     *
     * @param string|array $subject
     * @param string|array $content
     * @param boolean $send_technical_details - send user IP and browser
     * @return boolean
     */
    public static function mail_notification_admins($subject, $content, $send_technical_details = true)
    {
        global $conf, $user, $services;

        if (empty($subject) or empty($content)) {
            return false;
        }

        if (is_array($subject) or is_array($content)) {
            self::switch_lang_to($services['users']->getDefaultLanguage());

            if (is_array($subject)) {
                $subject = \Phyxo\Functions\Language::l10n_args($subject);
            }
            if (is_array($content)) {
                $content = \Phyxo\Functions\Language::l10n_args($content);
            }

            self::switch_lang_back();
        }

        $tpl_vars = [];
        if ($send_technical_details) {
            $tpl_vars['TECHNICAL'] = [
                'username' => stripslashes($user['username']),
                'ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            ];
        }

        return self::mail_admins(
            [
                'subject' => '[' . $conf['gallery_title'] . '] ' . $subject,
                'mail_title' => $conf['gallery_title'],
                'mail_subtitle' => $subject,
                'content' => $content,
                'content_format' => 'text/plain',
            ],
            [
                'filename' => 'notification_admin',
                'assign' => $tpl_vars,
            ]
        );
    }

    /**
     * Send a email to all administrators.
     * current user (if admin) is excluded
     * @see Mail::mail()
     *
     * @param array $args - as in Mail::mail()
     * @param array $tpl - as in Mail::mail()
     * @return boolean
     */
    public static function mail_admins($args = [], $tpl = [])
    {
        global $conf, $user, $conn, $services;

        if (empty($args['content']) and empty($tpl)) {
            return false;
        }

        $return = true;

        // get admins (except ourself)
        $query = 'SELECT u.' . $conf['user_fields']['username'] . ' AS name,';
        $query .= 'u.' . $conf['user_fields']['email'] . ' AS email FROM ' . USERS_TABLE . ' AS u';
        $query .= ' LEFT JOIN ' . USER_INFOS_TABLE . ' AS i ON i.user_id =  u.' . $conf['user_fields']['id'];
        $query .= ' WHERE i.status in (\'webmaster\',  \'admin\')';
        $query .= ' AND u.' . $conf['user_fields']['email'] . ' IS NOT NULL';
        $query .= ' AND i.user_id <> ' . $user['id'];
        $query .= ' ORDER BY name;';
        $admins = $conn->query2array($query);

        if (empty($admins)) {
            return $return;
        }

        self::switch_lang_to($services['users']->getDefaultLanguage());

        $return = self::mail($admins, $args, $tpl);

        self::switch_lang_back();

        return $return;
    }

    /**
     * Send an email to a group.
     * @see Mail::mail()
     *
     * @param int $group_id
     * @param array $args - as in Mail::mail()
     *       o language_selected: filters users of the group by language [default value empty]
     * @param array $tpl - as in Mail::mail()
     * @return boolean
     */
    public static function mail_group($group_id, $args = [], $tpl = [])
    {
        global $conf, $conn;

        if (empty($group_id) or (empty($args['content']) and empty($tpl))) {
            return false;
        }

        $return = true;

        // get distinct languages of targeted users
        $query = 'SELECT DISTINCT language FROM ' . USER_TABLE . ' AS u';
        $query .= ' LEFT JOIN ' . USERS_GROUP_TABLE . ' AS ug ON ' . $conf['user_fields']['id'] . ' = ug.user_id';
        $query .= ' LEFT JOIN ' . USER_INFOS_TABLE . ' AS ui ON ui.user_id = ug.user_id';
        $query .= ' WHERE group_id = ' . $group_id . ' AND ' . $conf['user_fields']['email'] . ' <> ""';

        if (!empty($args['language_selected'])) {
            $query .= ' AND language = \'' . $args['language_selected'] . '\'';
        }

        $languages = $conn->query2array($query, 'language');

        if (empty($languages)) {
            return $return;
        }

        foreach ($languages as $language) {
            // get subset of users in this group for a specific language
            $query = 'SELECT u.' . $conf['user_fields']['username'] . ' AS name,';
            $query .= 'u.' . $conf['user_fields']['email'] . ' AS email';
            $query .= ' FROM ' . USER_TABLE . ' AS u';
            $query .= ' LEFT JOIN ' . USERS_GROUP_TABLE . ' AS ug ON ' . $conf['user_fields']['id'] . ' = ug.user_id';
            $query .= ' LEFT JOIN ' . USER_INFOS_TABLE . ' AS ui ON ui.user_id = ug.user_id';
            $query .= ' WHERE group_id = ' . $group_id . ' AND ' . $conf['user_fields']['email'] . ' <> ""';
            $query .= ' AND language = \'' . $language . '\';';
            $users = $conn->query2array($query);

            if (empty($users)) {
                continue;
            }

            self::switch_lang_to($language);

            $return = self::mail(
                null,
                array_merge(
                    $args,
                    ['Bcc' => $users]
                ),
                $tpl
            );

            self::switch_lang_back();
        }

        return $return;
    }

    /**
     * Sends an email, using Piwigo specific informations.
     *
     * @param string|array $to
     * @param array $args
     *       o from: sender [default value webmaster email]
     *       o Cc: array of carbon copy receivers of the mail. [default value empty]
     *       o Bcc: array of blind carbon copy receivers of the mail. [default value empty]
     *       o subject [default value 'Phyxo']
     *       o content: content of mail [default value '']
     *       o content_format: format of mail content [default value 'text/plain']
     *       o email_format: global mail format [default value $conf_mail['default_email_format']]
     *       o theme: theme to use [default value $conf_mail['mail_theme']]
     *       o mail_title: main title of the mail [default value $conf['gallery_title']]
     *       o mail_subtitle: subtitle of the mail [default value subject]
     * @param array $tpl - use these options to define a custom content template file
     *       o filename
     *       o dirname (optional)
     *       o assign (optional)
     *
     * @return boolean
     */
    public static function mail($to, $args = [], $tpl = [])
    {
        global $conf, $conf_mail, $lang_info, $page, $services;

        if (empty($to) && empty($args['Cc']) && empty($args['Bcc'])) {
            return true;
        }

        if (!isset($conf_mail)) {
            $conf_mail = self::get_mail_configuration();
        }

        $message = new \Swift_Message();
        foreach (self::get_clean_recipients_list($to) as $recipient) {
            $message->addTo($recipient['email'], $recipient['name']);
        }

        //$mail->WordWrap = 76;
        $message->setCharSet('utf-8');

        // Compute root_path in order have complete path
        \Phyxo\Functions\URL::set_make_full_url();

        if (empty($args['from'])) {
            $from = [
                'email' => $conf_mail['email_webmaster'],
                'name' => $conf_mail['name_webmaster'],
            ];
        } else {
            $from = self::unformat_email($args['from']);
        }
        $message->setFrom($from['email'], $from['name']);
        $message->setReplyTo($from['email'], $from['name']);

        // Subject
        if (empty($args['subject'])) {
            $args['subject'] = 'Phyxo';
        }
        $args['subject'] = trim(preg_replace('#[\n\r]+#s', '', $args['subject']));
        $message->setSubject($args['subject']);

        // Cc
        if (!empty($args['Cc'])) {
            foreach (self::get_clean_recipients_list($args['Cc']) as $recipient) {
                $message->addCC($recipient['email'], $recipient['name']);
            }
        }

        // Bcc
        $Bcc = self::get_clean_recipients_list(@$args['Bcc']);
        if ($conf_mail['send_bcc_mail_webmaster']) {
            $Bcc[] = [
                'email' => \Phyxo\Functions\Utils::get_webmaster_mail_address(),
                'name' => '',
            ];
        }
        if (!empty($Bcc)) {
            foreach ($Bcc as $recipient) {
                $message->addBCC($recipient['email'], $recipient['name']);
            }
        }

        // theme
        if (empty($args['theme']) or !in_array($args['theme'], ['clear', 'dark'])) {
            $args['theme'] = $conf_mail['mail_theme'];
        }

        // content
        if (!isset($args['content'])) {
            $args['content'] = '';
        }

        // try to decompose subject like "[....] ...."
        if (!isset($args['mail_title']) and !isset($args['mail_subtitle'])) {
            if (preg_match('#^\[(.*)\](.*)$#', $args['subject'], $matches)) {
                $args['mail_title'] = $matches[1];
                $args['mail_subtitle'] = $matches[2];
            }
        }
        if (!isset($args['mail_title'])) {
            $args['mail_title'] = $conf['gallery_title'];
        }
        if (!isset($args['mail_subtitle'])) {
            $args['mail_subtitle'] = $args['subject'];
        }

        // content type
        if (empty($args['content_format'])) {
            $args['content_format'] = 'text/plain';
        }

        $content_type_list = [];
        if ($conf_mail['mail_allow_html'] && (empty($args['email_format']) || $args['email_format'] != 'text/plain')) {
            $content_type_list[] = 'text/html';
        }
        $content_type_list[] = 'text/plain';

        $contents = [];
        foreach ($content_type_list as $content_type) {
            // key compose of indexes witch allow to cache mail data
            $cache_key = $content_type . '-' . $lang_info['code'];

            if (!isset($conf_mail[$cache_key])) {
                // instanciate a new Template
                if (!isset($conf_mail[$cache_key]['theme'])) {
                    $conf_mail[$cache_key]['theme'] = self::get_mail_template($content_type);
                    \Phyxo\Functions\Plugin::trigger_notify('before_parse_mail_template', $cache_key, $content_type);
                }
                $template = &$conf_mail[$cache_key]['theme'];

                $template->set_filename('mail_header', 'header.tpl');
                $template->set_filename('mail_footer', 'footer.tpl');

                $template->assign(
                    [
                        'GALLERY_URL' => \Phyxo\Functions\URL::get_gallery_home_url(),
                        'GALLERY_TITLE' => isset($page['gallery_title']) ? $page['gallery_title'] : $conf['gallery_title'],
                        'VERSION' => $conf['show_version'] ? PHPWG_VERSION : '',
                        'PHPWG_URL' => defined('PHPWG_URL') ? PHPWG_URL : '',
                        'CONTENT_ENCODING' => \Phyxo\Functions\Utils::get_charset(),
                        'CONTACT_MAIL' => $conf_mail['email_webmaster'],
                    ]
                );

                if ($content_type == 'text/html') {
                    if ($template->isTemplateExists('global-mail-css.tpl')) {
                        $template->set_filename('global-css', 'global-mail-css.tpl');
                        $template->assign_var_from_handle('GLOBAL_MAIL_CSS', 'global-css');
                    }

                    if ($template->isTemplateExists('mail-css-' . $args['theme'] . '.tpl')) {
                        $template->set_filename('css', 'mail-css-' . $args['theme'] . '.tpl');
                        $template->assign_var_from_handle('MAIL_CSS', 'css');
                    }
                }
            }

            $template = &$conf_mail[$cache_key]['theme'];
            $template->assign(
                [
                    'MAIL_TITLE' => $args['mail_title'],
                    'MAIL_SUBTITLE' => $args['mail_subtitle'],
                ]
            );

            // Header
            $contents[$content_type] = $template->parse('mail_header', true);

            // Content
            // Stored in a temp variable, if a content template is used it will be assigned
            // to the $CONTENT template variable, otherwise it will be appened to the mail
            if ($args['content_format'] == 'text/plain' and $content_type == 'text/html') {
                // convert plain text to html
                $mail_content =
                    '<p>' .
                    nl2br(
                    preg_replace(
                        '/(https?:\/\/([-\w\.]+[-\w])+(:\d+)?(\/([\w\/_\.\#-]*(\?\S+)?[^\.\s])?)?)/i',
                        '<a href="$1">$1</a>',
                        htmlspecialchars($args['content'])
                    )
                ) .
                    '</p>';
            } elseif ($args['content_format'] == 'text/html' and $content_type == 'text/plain') {
                // convert html text to plain text
                $mail_content = strip_tags($args['content']);
            } else {
                $mail_content = $args['content'];
            }

            // Runtime template
            if (isset($tpl['filename'])) {
                if (isset($tpl['dirname'])) {
                    $template->set_template_dir($tpl['dirname'] . '/' . $content_type);
                }
                if ($template->isTemplateExists($tpl['filename'] . '.tpl')) {
                    $template->set_filename($tpl['filename'], $tpl['filename'] . '.tpl');
                    if (!empty($tpl['assign'])) {
                        $template->assign($tpl['assign']);
                    }
                    $template->assign('CONTENT', $mail_content);
                    $contents[$content_type] .= $template->parse($tpl['filename'], true);
                } else {
                    $contents[$content_type] .= $mail_content;
                }
            } else {
                $contents[$content_type] .= $mail_content;
            }

            // Footer
            $contents[$content_type] .= $template->parse('mail_footer', true);
        }

        // Undo Compute root_path in order have complete path
        \Phyxo\Functions\URL::unset_make_full_url();

        if (isset($contents['text/html'])) {
            $message->setBody(self::move_css_to_body($contents['text/html']), 'text/html');

            if (isset($contents['text/plain'])) {
                $message->addPart($contents['text/plain'], 'text/plain');
            }
        } else {
            $message->setBody($contents['text/plain'], 'text/plain');
        }

        if ($conf_mail['use_smtp']) {
            // now we need to split port number
            if (strpos($conf_mail['smtp_host'], ':') !== false) {
                list($smtp_host, $smtp_port) = explode(':', $conf_mail['smtp_host']);
            } else {
                $smtp_host = $conf_mail['smtp_host'];
                $smtp_port = 25;
            }

            $mail_transport = new \Swift_SmtpTransport($smtp_host, $smtp_port);
            if (!empty($conf_mail['smtp_secure']) and in_array($conf_mail['smtp_secure'], ['ssl', 'tls'])) {
                $mail_transport->setSecurity($conf_mail['smtp_secure']);
            }

            if (!empty($conf_mail['smtp_user']) && !empty($conf_mail['smtp_password'])) {
                $mail_transport->setUsername($conf_mail['smtp_user']);
                $mail_transport->setPassword($conf_mail['smtp_password']);
            }
        } else {
            $mail_transport = new \Swift_SendmailTransport();
        }

        try {
            $mailer = new \Swift_Mailer($mail_transport);
            $pre_result = \Phyxo\Functions\Plugin::trigger_change('before_send_mail', true, $to, $args, $mailer);
            $result = $mailer->send($message);
        } catch (\Exception $e) {
            $result = false;
        }

        return $result;
    }

    /**
     * Moves CSS rules contained in the <style> tag to inline CSS.
     * Used for compatibility with Gmail and such clients
     *
     * @param string $content
     * @return string
     */
    public static function move_css_to_body($content)
    {
        $e = new Emogrifier($content);
        return @$e->emogrify(); // @TODO: remove arobase
    }
}

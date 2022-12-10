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

namespace App\Controller\Admin;

use App\DataMapper\UserMapper;
use App\Form\DisplayConfigurationType;
use App\Form\UserInfosType;
use App\Repository\UserInfosRepository;
use App\Services\DerivativeService;
use Phyxo\Conf;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Image\WatermarkParams;
use Phyxo\TabSheet\TabSheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminConfigurationController extends AbstractController
{
    /** @var array<string> $main_checkboxes */
    private array $main_checkboxes = [
        'allow_user_registration',
        'obligatory_user_mail_address',
        'rate',
        'rate_anonymous',
        'email_admin_on_new_user',
        'allow_user_customization',
        'log',
        'history_admin',
        'history_guest',
    ];

    /** @var array<string> $sizes_checkboxes */
    private array $sizes_checkboxes = ['original_resize'];

    /** @var array<string> $comments_checkboxes */
    private  array $comments_checkboxes = [
        'activate_comments',
        'comments_forall',
        'comments_validation',
        'email_admin_on_comment',
        'email_admin_on_comment_validation',
        'user_can_delete_comment',
        'user_can_edit_comment',
        'email_admin_on_comment_edition',
        'email_admin_on_comment_deletion',
        'comments_author_mandatory',
        'comments_email_mandatory',
        'comments_enable_website',
    ];

    /** @var array<string, string> $sort_fields */
    private readonly array $sort_fields;

    /** @var array<string, string> $comments_order */
    private readonly array $comments_order;

    /** @var array<string, string> $mail_themes */
    private array $mail_themes;

    public function __construct(private readonly TranslatorInterface $translator)
    {
        $this->sort_fields = [
            '' => '',
            'file ASC' => $this->translator->trans('File name, A &rarr; Z', [], 'admin'),
            'file DESC' => $this->translator->trans('File name, Z &rarr; A', [], 'admin'),
            'name ASC' => $this->translator->trans('Photo title, A &rarr; Z', [], 'admin'),
            'name DESC' => $this->translator->trans('Photo title, Z &rarr; A', [], 'admin'),
            'date_creation DESC' => $this->translator->trans('Date created, new &rarr; old', [], 'admin'),
            'date_creation ASC' => $this->translator->trans('Date created, old &rarr; new', [], 'admin'),
            'date_available DESC' => $this->translator->trans('Date posted, new &rarr; old', [], 'admin'),
            'rating_score DESC' => $this->translator->trans('Rating score, high &rarr; low', [], 'admin'),
            'date_available ASC' => $this->translator->trans('Date posted, old &rarr; new', [], 'admin'),
            'rating_score ASC' => $this->translator->trans('Rating score, low &rarr; high', [], 'admin'),
            'hit DESC' => $this->translator->trans('Visits, high &rarr; low', [], 'admin'),
            'hit ASC' => $this->translator->trans('Visits, low &rarr; high', [], 'admin'),
            'id ASC' => $this->translator->trans('Numeric identifier, 1 &rarr; 9', [], 'admin'),
            'id DESC' => $this->translator->trans('Numeric identifier, 9 &rarr; 1', [], 'admin'),
            'rank ASC' => $this->translator->trans('Manual sort order', [], 'admin'),
        ];

        $this->comments_order = [
            'ASC' => $this->translator->trans('Show oldest comments first', [], 'admin'),
            'DESC' => $this->translator->trans('Show latest comments first', [], 'admin'),
        ];

        $this->mail_themes = [
            'clear' => $this->translator->trans('Clear', [], 'admin'),
            'dark' => $this->translator->trans('Dark', [], 'admin')
        ];
    }

    protected function setTabsheet(string $section = 'main'): TabSheet
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('main', $this->translator->trans('General', [], 'admin'), $this->generateUrl('admin_configuration'));
        $tabsheet->add('sizes', $this->translator->trans('Photo sizes', [], 'admin'), $this->generateUrl('admin_configuration', ['section' => 'sizes']));
        $tabsheet->add('watermark', $this->translator->trans('Watermark', [], 'admin'), $this->generateUrl('admin_configuration', ['section' => 'watermark']));
        $tabsheet->add('display', $this->translator->trans('Display', [], 'admin'), $this->generateUrl('admin_configuration_display'));
        $tabsheet->add('comments', $this->translator->trans('Comments', [], 'admin'), $this->generateUrl('admin_configuration', ['section' => 'comments']));
        $tabsheet->add('default', $this->translator->trans('Guest Settings', [], 'admin'), $this->generateUrl('admin_configuration_default'));
        $tabsheet->select($section);

        return $tabsheet;
    }

    public function index(
        string $section,
        Conf $conf,
        ParameterBagInterface $params,
        CsrfTokenManagerInterface $csrfTokenManager,
        ImageStandardParams $image_std_params
    ): Response {
        $tpl_params = [];

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_configuration', ['section' => $section]);
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_configuration_update', ['section' => $section]);
        $tpl_params['tabsheet'] = $this->setTabsheet($section);

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_configuration');

        switch ($section) {
            case 'main':
                $tpl_params = array_merge($tpl_params, $this->mainConfiguration($conf));
                break;

            case 'comments':
                $tpl_params = array_merge($tpl_params, $this->commentsConfiguration($conf));
                break;

            case 'sizes':
                $tpl_params = array_merge($tpl_params, $this->sizesConfiguration($conf, $image_std_params));
                break;

            case 'watermark':
                $tpl_params = array_merge($tpl_params, $this->watermarkConfiguration($conf, $params->get('themes_dir'), $params->get('local_dir'), $image_std_params));
                break;

            default:
                break;
        }

        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('authenticate');

        return $this->render('configuration_' . $section . '.html.twig', $tpl_params);
    }

    public function sizeRestore(ImageStandardParams $image_std_params, Conf $conf, TranslatorInterface $translator, DerivativeService $derivativeService): Response
    {
        $image_std_params->setAndSave($image_std_params->getDefaultSizes());
        $derivativeService->clearCache($image_std_params->getAllTypes(), $image_std_params->getAllTypes());
        $this->addFlash('success', $translator->trans('Your configuration settings have been saved', [], 'admin'));
        unset($conf['disabled_derivatives']);

        return $this->redirectToRoute('admin_configuration', ['section' => 'sizes']);
    }

    /** @phpstan-ignore-next-line */ // @FIX: define return type
    protected function mainConfiguration(Conf $conf): array
    {
        $tpl_params = [];

        if (!empty($conf['order_by_custom']) || !empty($conf['order_by_inside_category_custom'])) {
            $order_by = [''];
            $tpl_params['ORDER_BY_IS_CUSTOM'] = true;
        } else {
            $order_by = $conf['order_by_inside_category'];
        }

        $tpl_params['main'] = [
            'CONF_GALLERY_TITLE' => htmlspecialchars((string) $conf['gallery_title']),
            'CONF_PAGE_BANNER' => htmlspecialchars((string) $conf['page_banner']),
            'week_starts_on_options' => ['sunday' => $this->translator->trans('sunday', [], 'admin'), 'monday' => $this->translator->trans('monday', [], 'admin')],
            'week_starts_on_options_selected' => $conf['week_starts_on'],
            'mail_theme' => $conf['mail_theme'],
            'mail_theme_options' => $this->mail_themes,
            'order_by' => $order_by,
            'order_by_options' => $this->sort_fields,
        ];

        foreach ($this->main_checkboxes as $name_checkbox) {
            $tpl_params['main'][$name_checkbox] = $conf[$name_checkbox];
        }

        return $tpl_params;
    }

    /** @phpstan-ignore-next-line */ // @FIX: define return type
    protected function commentsConfiguration(Conf $conf): array
    {
        $tpl_params = [];

        $tpl_params['comments'] = [
            'NB_COMMENTS_PAGE' => $conf['nb_comment_page'],
            'comments_order' => $conf['comments_order'],
            'comments_order_options' => $this->comments_order
        ];

        foreach ($this->comments_checkboxes as $checkbox) {
            $tpl_params['comments'][$checkbox] = $conf[$checkbox];
        }

        return $tpl_params;
    }

    public function displayConfiguration(Request $request, Conf $conf, TranslatorInterface $translator): Response
    {
        $tpl_params = [];
        $tpl_params['tabsheet'] = $this->setTabsheet('display');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_configuration');

        $form = $this->createForm(DisplayConfigurationType::class, $conf);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($form->getData() as $confKey => $confParam) {
                $conf->addOrUpdateParam($confKey, $confParam['value'], $confParam['type']);
            }

            $this->addFlash('success', $translator->trans('Your configuration settings have been updated', [], 'admin'));

            return $this->redirectToRoute('admin_configuration_display');
        }

        $tpl_params['form'] = $form->createView();

        return $this->render('configuration_display.html.twig', $tpl_params);
    }

    /** @phpstan-ignore-next-line */ // @FIX: define return type
    protected function sizesConfiguration(Conf $conf, ImageStandardParams $image_std_params): array
    {
        $tpl_params = [];

        $tpl_params['sizes'] = [
            'original_resize_maxwidth' => $conf['original_resize_maxwidth'],
            'original_resize_maxheight' => $conf['original_resize_maxheight'],
            'original_resize_quality' => $conf['original_resize_quality'],
        ];

        foreach ($this->sizes_checkboxes as $checkbox) {
            $tpl_params['sizes'][$checkbox] = $conf[$checkbox];
        }

        // derivatives = multiple size
        $enabled = $image_std_params->getDefinedTypeMap();
        if (!empty($conf['disabled_derivatives'])) {
            $disabled = $conf['disabled_derivatives'];
        } else {
            $disabled = [];
        }

        foreach ($image_std_params->getAllTypes() as $type) {
            $tpl_var = [];
            $tpl_var['must_square'] = ($type == ImageStandardParams::IMG_SQUARE ? true : false);
            $tpl_var['must_enable'] = ($type == ImageStandardParams::IMG_SQUARE || $type == ImageStandardParams::IMG_THUMB || $type == $conf['derivative_default_size']) ? true : false;

            if (!empty($enabled[$type]) && ($params = $enabled[$type])) {
                $tpl_var['enabled'] = true;
            } else {
                $tpl_var['enabled'] = false;
                $params = $disabled[$type];
            }

            if ($params) {
                [$tpl_var['w'], $tpl_var['h']] = $params->sizing->ideal_size;
                if (($tpl_var['crop'] = round(100 * $params->sizing->max_crop)) > 0) {
                    [$tpl_var['minw'], $tpl_var['minh']] = $params->sizing->min_size;
                } else {
                    $tpl_var['minw'] = $tpl_var['minh'] = "";
                }
            }
            $tpl_params['derivatives'][$type] = $tpl_var;
        }
        $tpl_params['resize_quality'] = $image_std_params->getQuality();

        $tpl_vars = [];
        $now = time();
        foreach ($image_std_params->getCustoms() as $custom => $time) {
            $tpl_vars[$custom] = ($now - $time >= 24 * 3600) ? $this->translator->trans('today', [], 'admin') : $time;
        }
        $tpl_params['custom_derivatives'] = $tpl_vars;

        return $tpl_params;
    }

    /** @phpstan-ignore-next-line */ // @FIX: define return type
    protected function watermarkConfiguration(Conf $conf, string $themesDir, string $localDir, ImageStandardParams $image_std_params): array
    {
        $tpl_params = [];

        $watermark_files = [];

        foreach (glob($themesDir . '/*/watermarks/*.png') as $file) {
            $watermark_files[] = 'themes' . substr((string) $file, strlen($themesDir));
        }

        if (($glob = glob($localDir . '/watermarks/*.png')) !== false) {
            foreach ($glob as $file) {
                $watermark_files[] = 'local' . substr($file, strlen($localDir));
            }
        }

        $watermark_filemap = ['' => '---'];
        foreach ($watermark_files as $file) {
            $display = basename($file);
            $watermark_filemap[$file] = $display;
        }
        $tpl_params['watermark_files'] = $watermark_filemap;

        $wm = $image_std_params->getWatermark();

        $position = 'custom';
        if ($wm->xpos == 0 and $wm->ypos == 0) {
            $position = 'topleft';
        }
        if ($wm->xpos == 100 and $wm->ypos == 0) {
            $position = 'topright';
        }
        if ($wm->xpos == 50 and $wm->ypos == 50) {
            $position = 'middle';
        }
        if ($wm->xpos == 0 and $wm->ypos == 100) {
            $position = 'bottomleft';
        }
        if ($wm->xpos == 100 and $wm->ypos == 100) {
            $position = 'bottomright';
        }

        if ($wm->xrepeat != 0) {
            $position = 'custom';
        }

        $tpl_params['watermark'] = [
            'file' => $wm->file,
            'minw' => $wm->min_size[0],
            'minh' => $wm->min_size[1],
            'xpos' => $wm->xpos,
            'ypos' => $wm->ypos,
            'xrepeat' => $wm->xrepeat,
            'opacity' => $wm->opacity,
            'position' => $position,
        ];


        return $tpl_params;
    }

    public function defaultConfiguration(Request $request, UserMapper $userMapper, UserInfosRepository $userInfosRepository, TranslatorInterface $translator): Response
    {
        $tpl_params = [];
        $tpl_params['tabsheet'] = $this->setTabsheet('default');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_configuration');

        $form = $this->createForm(UserInfosType::class, $userMapper->getDefaultUser()->getUserInfos());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userInfos = $form->getData();
            $userInfosRepository->updateInfos($userInfos);

            $this->addFlash('info', $translator->trans('Guest user settings have been updated', [], 'admin'));

            return $this->redirectToRoute('admin_configuration_default');
        }

        $tpl_params['form'] = $form->createView();

        return $this->render('configuration_default.html.twig', $tpl_params);
    }

    public function update(
        Request $request,
        string $section,
        Conf $conf,
        string $localDir,
        ImageStandardParams $image_std_params,
        DerivativeService $derivativeService
    ): Response {
        $watermarke = [];
        $conf_updated = false;
        $error = false;

        if ($request->isMethod('POST')) {
            if ($section === 'main') {
                if ($request->request->get('gallery_title') && $conf['gallery_title'] !== $request->request->get('gallery_title')) {
                    $conf_updated = true;
                    if (!$conf['allow_html_descriptions']) {
                        $conf['gallery_title'] = strip_tags($request->request->get('gallery_title'));
                    } else {
                        $conf['gallery_title'] = $request->request->get('gallery_title');
                    }
                }

                if ($request->request->get('page_banner') && $conf['page_banner'] !== $request->request->get('page_banner')) {
                    $conf_updated = true;
                    $conf['page_banner'] = $request->request->get('page_banner');
                }

                if ($request->request->get('week_starts_on') && $conf['week_starts_on'] !== $request->request->get('week_starts_on')) {
                    $conf_updated = true;
                    $conf['week_starts_on'] = $request->request->get('week_starts_on');
                }

                if (empty($conf['order_by_custom']) && empty($conf['order_by_inside_category_custom'])) {
                    if ($order_by = $request->request->all('order_by')) {
                        $used = [];
                        foreach ($order_by as $i => $val) {
                            if (empty($val) || isset($used[$val])) {
                                unset($order_by[$i]);
                            } else {
                                $used[$val] = true;
                            }
                        }
                        if (count($order_by) === 0) {
                            $this->addFlash('error', $this->translator->trans('No order field selected', [], 'admin'));
                        } else {
                            // limit to the number of available parameters
                            $order_by = $order_by_inside_category = array_slice($order_by, 0, (int) ceil(count($this->sort_fields) / 2));

                            // there is no rank outside categories
                            if (($i = array_search('rank ASC', $order_by)) !== false) {
                                unset($order_by[$i]);
                            }

                            // must define a default order_by if user want to order by rank only
                            if (count($order_by) === 0) {
                                $order_by = ['id ASC'];
                            }

                            $new_order_by_value = 'ORDER BY ' . implode(', ', $order_by);
                            if ($conf['order_by'] !== $new_order_by_value) {
                                $conf_updated = true;
                                $conf['order_by'] = $new_order_by_value;
                            }
                            $new_order_by_value = 'ORDER BY ' . implode(', ', $order_by_inside_category);
                            if ($conf['order_by_inside_category'] !== $new_order_by_value) {
                                $conf_updated = true;
                                $conf['order_by_inside_category'] = $new_order_by_value;
                            }
                        }
                    } else {
                        $this->addFlash('error', $this->translator->trans('No order field selected', [], 'admin'));
                    }
                }

                foreach ($this->main_checkboxes as $name_checkbox) {
                    $new_value = $request->request->get($name_checkbox) !== null;

                    if ($conf[$name_checkbox] !== $new_value) {
                        $conf_updated = true;
                        $conf[$name_checkbox] = $new_value;
                    }
                }

                if (($mail_theme = $request->request->get('mail_theme')) && isset($this->mail_themes[$mail_theme])) {
                    if ($conf['mail_theme'] !== $mail_theme) {
                        $conf_updated = true;
                        $conf['mail_theme'] = $mail_theme;
                    }
                }
            } elseif ($section === 'comments') {
                if ($request->request->get('nb_comment_page')) {
                    $nb_comments = (int) $request->request->get('nb_comment_page');
                    if ($nb_comments < 5 || $nb_comments > 50) {
                        $this->addFlash('error', $this->translator->trans('The number of comments a page must be between 5 and 50 included.', [], 'admin'));
                        $error = true;
                    } elseif ($conf['nb_comment_page'] !== $nb_comments) {
                        $conf['nb_comment_page'] = $nb_comments;
                        $conf_updated = true;
                    }
                }

                if ($request->request->get('comments_order')) {
                    $comments_order = $request->request->get('comments_order');
                    if (isset($comments_order[$comments_order]) && $conf['$comments_order'] !== $comments_order) {
                        $conf_updated = true;
                        $conf['comments_order'] = $request->request->get('comments_order');
                    }
                }

                foreach ($this->comments_checkboxes as $name_checkbox) {
                    $new_value = !empty($request->request->get($name_checkbox));

                    if ($conf[$name_checkbox] !== $new_value) {
                        $conf_updated = true;
                        $conf[$name_checkbox] = $new_value;
                    }
                }
            } elseif ($section === 'watermark') {
                $watermark = ['file' => '', 'xpos' => '', 'ypos' => '', 'xrepeat' => '', 'opacity' => '', 'minh' => '', 'minw' => ''];
                if ($request->files->get('watermarkImage')) {
                    $watermarkImage = $request->files->get('watermarkImage');

                    [, , $type] = getimagesize($watermarkImage->getPathName());
                    if ($type !== IMAGETYPE_PNG) {
                        $this->addFlash('error', $this->translator->trans('Allowed file types: {type}.', ['type' => 'PNG'], 'admin'));
                        $error = true;
                    } else {
                        $upload_dir = $localDir . '/watermarks';

                        try {
                            $fs = new Filesystem();
                            $fs->mkdir($upload_dir);

                            $watermarke['file'] = \Phyxo\Functions\Utils::get_filename_wo_extension($watermarkImage->getClientOriginalName()) . '.png';
                            $watermarkImage->move($upload_dir, $watermarke['file']);
                        } catch (\Exception) {
                            $this->addFlash('error', $this->translator->trans('Add write access to the "{directory}" directory', ['directory' => $upload_dir], 'admin'));
                            $error = true;
                        }
                    }
                } elseif ($request->request->all('watermark')['file']) {
                    $watermark['file'] = $request->request->all('watermark')['file'];
                } else {
                    $error = true;
                }

                if (!$error) {
                    if ($request->request->all('watermark')['position'] === 'topleft') {
                        $watermark['xpos'] = 0;
                        $watermark['ypos'] = 0;
                    } elseif ($request->request->all('watermark')['position'] === 'topright') {
                        $watermark['xpos'] = 100;
                        $watermark['ypos'] = 0;
                    } elseif ($request->request->all('watermark')['position'] === 'middle') {
                        $watermark['xpos'] = 50;
                        $watermark['ypos'] = 50;
                    } elseif ($request->request->all('watermark')['position'] === 'bottomleft') {
                        $watermark['xpos'] = 0;
                        $watermark['ypos'] = 100;
                    } elseif ($request->request->all('watermark')['position'] === 'bottomright') {
                        $watermark['xpos'] = 100;
                        $watermark['ypos'] = 100;
                    } elseif ($request->request->all('watermark')['position'] === 'custom') {
                        $xpos = intval($request->request->all('watermark')['xpos']);
                        $ypos = intval($request->request->all('watermark')['ypos']);
                        if ($xpos < 0 || $xpos > 100) {
                            $this->addFlash('error', 'xpos [0..100]');
                            $error = true;
                        } else {
                            $watermark['xpos'] = $xpos;
                        }
                        if ($ypos < 0 || $ypos > 100) {
                            $this->addFlash('error', 'ypos [0..100]');
                            $error = true;
                        } else {
                            $watermark['ypos'] = $ypos;
                        }
                    }

                    $opacity = intval($request->request->all('watermark')['opacity']);
                    if ($opacity <= 0 || $opacity > 100) {
                        $this->addFlash('error', 'opacity (0..100]');
                        $error = true;
                    } else {
                        $watermark['opacity'] = $opacity;
                    }

                    if ($request->request->all('watermark')['xrepeat']) {
                        $watermark['xrepeat'] = intval($request->request->all('watermark')['xrepeat']);
                    } else {
                        $watermark['xrepeat'] = 0;
                    }

                    if ($request->request->all('watermark')['minw']) {
                        $watermark['minw'] = intval($request->request->all('watermark')['minw']);
                    } else {
                        $watermark['minw'] = 500;
                    }

                    if ($request->request->all('watermark')['minh']) {
                        $watermark['minh'] = intval($request->request->all('watermark')['minh']);
                    } else {
                        $watermark['minh'] = 500;
                    }
                }

                if (!$error) {
                    $watermark_params = new WatermarkParams();
                    $watermark_params->file = $watermark['file'];
                    $watermark_params->xpos = $watermark['xpos'];
                    $watermark_params->ypos = $watermark['ypos'];
                    $watermark_params->xrepeat = $watermark['xrepeat'];
                    $watermark_params->opacity = $watermark['opacity'];
                    $watermark_params->min_size = [$watermark['minw'], $watermark['minh']];

                    $old_watermark = $image_std_params->getWatermark();
                    $watermark_changed = $watermark_params->file != $old_watermark->file
                            || $watermark_params->xpos != $old_watermark->xpos
                            || $watermark_params->ypos != $old_watermark->ypos
                            || $watermark_params->xrepeat != $old_watermark->xrepeat
                            || $watermark_params->opacity != $old_watermark->opacity;

                    // save the new watermark configuration
                    $image_std_params->setWatermark($watermark_params);

                    // do we have to regenerate the derivatives (and which types)?
                    $changed_types = [];

                    foreach ($image_std_params->getDefinedTypeMap() as $type => $params) {
                        $old_use_watermark = $params->use_watermark;
                        $image_std_params->applyWatermark($params);

                        $changed = $params->use_watermark != $old_use_watermark;
                        if (!$changed and $params->use_watermark) {
                            $changed = $watermark_changed;
                        }
                        if (!$changed and $params->use_watermark) {
                            // if thresholds change and before/after the threshold is lower than the corresponding derivative side -> some derivatives might switch the watermark
                            $changed |= $watermark_params->min_size[0] != $old_watermark->min_size[0] && ($watermark_params->min_size[0] < $params->max_width() || $old_watermark->min_size[0] < $params->max_width());
                            $changed |= $watermark_params->min_size[1] != $old_watermark->min_size[1] && ($watermark_params->min_size[1] < $params->max_height() || $old_watermark->min_size[1] < $params->max_height());
                        }

                        if ($changed) {
                            $params->last_mod_time = time();
                            $changed_types[] = $type;
                        }
                    }

                    $image_std_params->save();

                    if (count($changed_types)) {
                        $derivativeService->clearCache($changed_types, $image_std_params->getAllTypes());
                    }

                    $this->addFlash('success', $this->translator->trans('Your configuration settings have been saved', [], 'admin'));
                }
            } elseif ($section === 'sizes') {
                // original resize
                $original_fields = [
                    'original_resize',
                    'original_resize_maxwidth',
                    'original_resize_maxheight',
                    'original_resize_quality',
                ];

                $updates = [];

                foreach ($original_fields as $field) {
                    $value = $request->request->get($field) ?? null;
                    $updates[$field] = $value;
                }

                $errors = [];
                foreach (\Phyxo\Functions\Upload::save_upload_form_config($updates, $errors, $errors) as $update) {
                    $conf[$update['param']] = $update['value'];
                }

                if ($request->request->get('resize_quality') < 50 || $request->request->get('resize_quality') > 98) {
                    $this->addFlash('error', 'resize_quality [50..98]');
                    $error = true;
                }

                $pderivatives = $request->request->all('d');

                // step 1 - sanitize HTML input
                foreach ($pderivatives as $type => &$pderivative) {
                    if ($pderivative['must_square'] = ($type == ImageStandardParams::IMG_SQUARE ? true : false)) {
                        $pderivative['h'] = $pderivative['w'];
                        $pderivative['minh'] = $pderivative['minw'] = $pderivative['w'];
                        $pderivative['crop'] = 100;
                    }
                    $pderivative['must_enable'] = ($type == ImageStandardParams::IMG_SQUARE || $type == ImageStandardParams::IMG_THUMB || $type == $conf['derivative_default_size']) ? true : false;
                    $pderivative['enabled'] = isset($pderivative['enabled']) || $pderivative['must_enable'] ? true : false;

                    if (isset($pderivative['crop'])) {
                        $pderivative['crop'] = 100;
                        $pderivative['minw'] = $pderivative['w'];
                        $pderivative['minh'] = $pderivative['h'];
                    } else {
                        $pderivative['crop'] = 0;
                        $pderivative['minw'] = null;
                        $pderivative['minh'] = null;
                    }
                }

                // step 2 - check validity
                $prev_w = $prev_h = 0;
                foreach ($image_std_params->getAllTypes() as $type) {
                    $pderivative = $pderivatives[$type];
                    if (!$pderivative['enabled']) {
                        continue;
                    }

                    if ($type == ImageStandardParams::IMG_THUMB) {
                        $w = intval($pderivative['w']);
                        if ($w <= 0) {
                            $errors[$type]['w'] = '>0';
                        }

                        $h = intval($pderivative['h']);
                        if ($h <= 0) {
                            $errors[$type]['h'] = '>0';
                        }

                        if (max($w, $h) <= $prev_w) {
                            $errors[$type]['w'] = $errors[$type]['h'] = '>' . $prev_w;
                        }
                    } else {
                        $v = intval($pderivative['w']);
                        if ($v <= 0 or $v <= $prev_w) {
                            $errors[$type]['w'] = '>' . $prev_w;
                        }

                        $v = intval($pderivative['h']);
                        if ($v <= 0 or $v <= $prev_h) {
                            $errors[$type]['h'] = '>' . $prev_h;
                        }
                    }

                    if ((is_countable($errors) ? count($errors) : 0) == 0) {
                        $prev_w = intval($pderivative['w']);
                        $prev_h = intval($pderivative['h']);
                    }
                }

                // step 3 - save data
                if ((is_countable($errors) ? count($errors) : 0) == 0) {
                    $quality_changed = $image_std_params->getQuality() != intval($request->request->get('resize_quality'));
                    $image_std_params->setQuality(intval($request->request->get('resize_quality')));

                    $enabled = $image_std_params->getDefinedTypeMap();
                    if (!empty($conf['disabled_derivatives'])) {
                        $disabled = $conf['disabled_derivatives'];
                    } else {
                        $disabled = [];
                    }
                    $changed_types = [];

                    foreach ($image_std_params->getAllTypes() as $type) {
                        $pderivative = $pderivatives[$type];

                        if ($pderivative['enabled']) {
                            $derivative_params = new \Phyxo\Image\SizingParams(
                                [intval($pderivative['w']), intval($pderivative['h'])],
                                round($pderivative['crop'] / 100, 2),
                                [intval($pderivative['minw']), intval($pderivative['minh'])]
                            );
                            $new_params = new \Phyxo\Image\DerivativeParams($derivative_params);

                            $image_std_params->applyWatermark($new_params);

                            if (isset($enabled[$type])) {
                                $old_params = $enabled[$type];
                                $same = true;
                                if (!\Phyxo\Image\DerivativeParams::size_equals($old_params->sizing->ideal_size, $new_params->sizing->ideal_size)
                                    || $old_params->sizing->max_crop != $new_params->sizing->max_crop) {
                                    $same = false;
                                }

                                if ($same
                                        && $new_params->sizing->max_crop != 0
                                        && !\Phyxo\Image\DerivativeParams::size_equals($old_params->sizing->min_size, $new_params->sizing->min_size)) {
                                    $same = false;
                                }

                                if (!$same) {
                                    $new_params->last_mod_time = time();
                                    $changed_types[] = $type;
                                } else {
                                    $new_params->last_mod_time = $old_params->last_mod_time;
                                }
                                $enabled[$type] = $new_params;
                            } else { // now enabled, before was disabled
                                $enabled[$type] = $new_params;
                                unset($disabled[$type]);
                            }
                        } else { // disabled
                            if (isset($enabled[$type])) { // now disabled, before was enabled
                                $changed_types[] = $type;
                                $disabled[$type] = $enabled[$type];
                                unset($enabled[$type]);
                            }
                        }
                    }

                    $enabled_by = []; // keys ordered by all types
                    foreach ($image_std_params->getAllTypes() as $type) {
                        if (isset($enabled[$type])) {
                            $enabled_by[$type] = $enabled[$type];
                        }
                    }

                    foreach (array_keys($image_std_params->getCustoms()) as $custom) {
                        if ($request->request->get('delete_custom_derivative_' . $custom)) {
                            $changed_types[] = $custom;
                            $image_std_params->unsetCustom($custom);
                        }
                    }

                    $image_std_params->setAndSave($enabled_by);
                    if ((is_countable($disabled) ? count($disabled) : 0) === 0) {
                        unset($conf['disabled_derivatives']);
                    } else {
                        $conf->addOrUpdateParam('disabled_derivatives', $disabled, 'base64');
                    }

                    if (count($changed_types)) {
                        $derivativeService->clearCache($changed_types, $image_std_params->getAllTypes());
                    }
                }
            }

            if ($conf_updated && !$error) {
                $this->addFlash('success', $this->translator->trans('Your configuration settings have been saved', [], 'admin'));
            }
        }

        return $this->redirectToRoute('admin_configuration', ['section' => $section]);
    }
}

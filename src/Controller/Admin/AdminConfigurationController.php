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

use App\Enum\ConfEnum;
use Phyxo\Functions\Utils;
use Exception;
use Phyxo\Image\SizingParams;
use Phyxo\Image\DerivativeParams;
use App\DataMapper\UserMapper;
use App\Enum\ImageSizeType;
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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin')]
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
    private array $comments_checkboxes = [
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

    #[Route('/configuration/{section}', name: 'admin_configuration', defaults: ['section' => 'main'], requirements: ['section' => 'main|sizes|watermark|comments'])]
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
                $tpl_params = array_merge($tpl_params, $this->watermarkConfiguration($params->get('themes_dir'), $params->get('local_dir'), $image_std_params));
                break;

            default:
                break;
        }

        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('authenticate');

        return $this->render('configuration_' . $section . '.html.twig', $tpl_params);
    }

    #[Route('/configuration/sizes/restore', 'admin_configuration_size_restore')]
    public function sizeRestore(ImageStandardParams $image_std_params, Conf $conf, TranslatorInterface $translator, DerivativeService $derivativeService): Response
    {
        $image_std_params->setAndSave($image_std_params->getDefaultSizes());
        $derivativeService->clearCache(ImageSizeType::getAllTypes(), ImageSizeType::getAllTypes());
        $this->addFlash('success', $translator->trans('Your configuration settings have been saved', [], 'admin'));
        unset($conf['disabled_derivatives']);

        return $this->redirectToRoute('admin_configuration', ['section' => 'sizes']);
    }

    /**
     * @return array<string, mixed>
     */
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
            'CONF_GALLERY_TITLE' => $conf['gallery_title'],
            'CONF_PAGE_BANNER' => $conf['page_banner'],
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

    /**
     * @return array<string, mixed>
     */
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

    #[Route('/configuration/display', 'admin_configuration_display')]
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

    /**
     * @return array<string, mixed>
     */
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
        $disabled = empty($conf['disabled_derivatives']) ? [] : $conf['disabled_derivatives'];

        foreach (ImageSizeType::getAllTypes() as $imageType) {
            $tpl_var = [];
            $tpl_var['must_square'] = $imageType === ImageSizeType::SQUARE;
            $tpl_var['must_enable'] = $imageType === ImageSizeType::SQUARE || $imageType === ImageSizeType::THUMB || $imageType === $conf['derivative_default_size'];

            if (!empty($enabled[$imageType->value])) {
                $params = $enabled[$imageType->value];
                $tpl_var['enabled'] = true;
            } else {
                $tpl_var['enabled'] = false;
                $params = $disabled[$imageType->value];
            }

            if ($params) {
                [$tpl_var['w'], $tpl_var['h']] = $params->getSizing()->getIdealSize();
                if (($tpl_var['crop'] = round(100 * $params->getSizing()->getMaxCrop())) > 0) {
                    [$tpl_var['minw'], $tpl_var['minh']] = $params->getSizing()->getMinSize();
                } else {
                    $tpl_var['minw'] = $tpl_var['minh'] = "";
                }
            }

            $tpl_params['derivatives'][$imageType->value] = $tpl_var;
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

    /**
     * @return array<string, mixed>
     */
    protected function watermarkConfiguration(string $themesDir, string $localDir, ImageStandardParams $image_std_params): array
    {
        $tpl_params = [];

        $watermark_files = [];

        foreach (glob($themesDir . '/*/watermarks/*.png') as $file) {
            $watermark_files[] = 'themes' . substr($file, strlen($themesDir));
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
        if ($wm->getXpos() == 0 && $wm->getYpos() == 0) {
            $position = 'topleft';
        }

        if ($wm->getXpos() == 100 && $wm->getYpos() == 0) {
            $position = 'topright';
        }

        if ($wm->getXpos() == 50 && $wm->getYpos() == 50) {
            $position = 'middle';
        }

        if ($wm->getXpos() == 0 && $wm->getYpos() == 100) {
            $position = 'bottomleft';
        }

        if ($wm->getXpos() == 100 && $wm->getYpos() == 100) {
            $position = 'bottomright';
        }

        if ($wm->getXrepeat() !== 0) {
            $position = 'custom';
        }

        $tpl_params['watermark'] = [
            'file' => $wm->getFile(),
            'minw' => $wm->getMinSize()[0],
            'minh' => $wm->getMinSize()[1],
            'xpos' => $wm->getXpos(),
            'ypos' => $wm->getYpos(),
            'xrepeat' => $wm->getXrepeat(),
            'opacity' => $wm->getOpacity(),
            'position' => $position,
        ];

        return $tpl_params;
    }

    #[Route('/configuration/default', 'admin_configuration_default')]
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

    #[Route('/configuration/{section}/update', 'admin_configuration_update', methods: 'POST', defaults: ['section' => 'main'], requirements: ['section' => 'main|sizes|watermark|comments'])]
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
                    $conf->addOrUpdateParam(
                        'gallery_title',
                        $conf['allow_html_descriptions'] ? $request->request->get('gallery_title') : strip_tags($request->request->get('gallery_title')),
                        ConfEnum::STRING
                    );
                }

                if ($request->request->get('page_banner') && $conf['page_banner'] !== $request->request->get('page_banner')) {
                    $conf_updated = true;
                    $conf->addOrUpdateParam('page_banner', $request->request->get('page_banner'), ConfEnum::STRING);
                }

                if ($request->request->get('week_starts_on') && $conf['week_starts_on'] !== $request->request->get('week_starts_on')) {
                    $conf_updated = true;
                    $conf->addOrUpdateParam('week_starts_on', $request->request->get('week_starts_on'), ConfEnum::STRING);
                }

                if (empty($conf['order_by_custom']) && empty($conf['order_by_inside_category_custom'])) {
                    if ($new_order_by = $request->request->all('order_by')) {
                        $order_by = [];
                        foreach ($new_order_by as $order) {
                            $order_by[] = explode(' ', (string) $order);
                        }

                        // limit to the number of available parameters
                        $order_by = array_slice($order_by, 0, (int) ceil(count($this->sort_fields) / 2));
                        $order_by_inside_category = $order_by;

                        // must define a default order_by if user want to order by rank only
                        if ($order_by === []) {
                            $order_by = ['id', 'ASC'];
                        }

                        // there is no rank outside categories
                        $order_by = array_filter($order_by, fn ($order): bool => json_encode($order) !== json_encode(['rank', 'ASC']));
                        if (json_encode($conf['order_by']) !== json_encode($order_by)) {
                            $conf->addOrUpdateParam('order_by', $order_by, ConfEnum::JSON);
                            $conf_updated = true;
                        }

                        if (json_encode($conf['order_by_inside_category']) !== json_encode($order_by_inside_category)) {
                            $conf->addOrUpdateParam('order_by_inside_category', $order_by_inside_category, ConfEnum::JSON);
                            $conf_updated = true;
                        }
                    } else {
                        $this->addFlash('error', $this->translator->trans('No order field selected', [], 'admin'));
                    }
                }

                foreach ($this->main_checkboxes as $name_checkbox) {
                    $new_value = $request->request->get($name_checkbox) !== null;

                    if ($conf[$name_checkbox] !== $new_value) {
                        $conf_updated = true;
                        $conf->addOrUpdateParam($name_checkbox, $new_value, ConfEnum::BOOLEAN);
                    }
                }

                if (($mail_theme = $request->request->get('mail_theme')) && isset($this->mail_themes[$mail_theme]) && $conf['mail_theme'] !== $mail_theme) {
                    $conf_updated = true;
                    $conf->addOrUpdateParam('mail_theme', $mail_theme, ConfEnum::STRING);
                }
            } elseif ($section === 'comments') {
                if ($request->request->get('nb_comment_page')) {
                    $nb_comments = (int) $request->request->get('nb_comment_page');
                    if ($nb_comments < 5 || $nb_comments > 50) {
                        $this->addFlash('error', $this->translator->trans('The number of comments a page must be between 5 and 50 included.', [], 'admin'));
                        $error = true;
                    } elseif ($conf['nb_comment_page'] !== $nb_comments) {
                        $conf_updated = true;
                        $conf->addOrUpdateParam('nb_comment_page', $nb_comments, ConfEnum::INTEGER);
                    }
                }

                if ($request->request->get('comments_order')) {
                    $comments_order = $request->request->get('comments_order');
                    if (isset($comments_order[$comments_order]) && $conf['$comments_order'] !== $comments_order) {
                        $conf_updated = true;
                        $conf->addOrUpdateParam('comments_order', $request->request->get('comments_order'), ConfEnum::STRING);
                    }
                }

                foreach ($this->comments_checkboxes as $name_checkbox) {
                    $new_value = !empty($request->request->get($name_checkbox));

                    if ($conf[$name_checkbox] !== $new_value) {
                        $conf_updated = true;
                        $conf->addOrUpdateParam($name_checkbox, $new_value, ConfEnum::BOOLEAN);
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

                            $watermarke['file'] = Utils::getFilenameWithoutExtension($watermarkImage->getClientOriginalName()) . '.png';
                            $watermarkImage->move($upload_dir, $watermarke['file']);
                        } catch (Exception) {
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
                    $watermark_params->setFile($watermark['file'])
                        ->setXpos($watermark['xpos'])
                        ->setYpos($watermark['ypos'])
                        ->setXrepeat($watermark['xrepeat'])
                        ->setOpacity($watermark['opacity']);
                    $watermark_params->setMinSize([$watermark['minw'], $watermark['minh']]);

                    $old_watermark = $image_std_params->getWatermark();
                    $watermark_changed = $watermark_params->getFile() !== $old_watermark->getFile()
                            || $watermark_params->getXpos() !== $old_watermark->getXpos()
                            || $watermark_params->getYpos() !== $old_watermark->getYpos()
                            || $watermark_params->getXrepeat() !== $old_watermark->getXrepeat()
                            || $watermark_params->getOpacity() !== $old_watermark->getOpacity();

                    // save the new watermark configuration
                    $image_std_params->setWatermark($watermark_params);

                    // do we have to regenerate the derivatives (and which types)?
                    $changed_types = [];

                    foreach ($image_std_params->getDefinedTypeMap() as $type => $params) {
                        $old_use_watermark = $params->use_watermark;
                        $image_std_params->applyWatermark($params);

                        $changed = $params->use_watermark != $old_use_watermark;
                        if (!$changed && $params->use_watermark) {
                            $changed = $watermark_changed;
                        }

                        if (!$changed && $params->use_watermark) {
                            // if thresholds change and before/after the threshold is lower than the corresponding derivative side -> some derivatives might switch the watermark
                            $changed |= $watermark_params->getMinSize()[0] != $old_watermark->getMinSize()[0]
                                    && ($watermark_params->getMinSize()[0] < $params->maxWidth() || $old_watermark->getMinSize()[0] < $params->maxWidth());
                            $changed |= $watermark_params->getMinSize()[1] != $old_watermark->getMinSize()[1]
                                    && ($watermark_params->getMinSize()[1] < $params->maxHeight() || $old_watermark->getMinSize()[1] < $params->maxHeight());
                        }

                        if ($changed) {
                            $params->last_mod_time = time();
                            $changed_types[] = ImageSizeType::from($type);
                        }
                    }

                    $image_std_params->save();

                    if ($changed_types !== []) {
                        $derivativeService->clearCache($changed_types, ImageSizeType::getAllTypes());
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
                foreach (self::saveUploadFormConfig($updates, $errors, $errors) as $update) {
                    $conf->addOrUpdateParam($update['param'], $update['value'], ConfEnum::STRING);
                }

                if ($request->request->get('resize_quality') < 50 || $request->request->get('resize_quality') > 98) {
                    $this->addFlash('error', 'resize_quality [50..98]');
                    $error = true;
                }

                $pderivatives = $request->request->all('d');

                // step 1 - sanitize HTML input
                foreach ($pderivatives as $type => &$pderivative) {
                    if ($pderivative['must_square'] = ($type === ImageSizeType::SQUARE->value)) {
                        $pderivative['h'] = $pderivative['w'];
                        $pderivative['minh'] = $pderivative['minw'] = $pderivative['w'];
                        $pderivative['crop'] = 100;
                    }

                    $pderivative['must_enable'] = $type === ImageSizeType::SQUARE->value || $type === ImageSizeType::THUMB->value || $type === $conf['derivative_default_size'];
                    $pderivative['enabled'] = isset($pderivative['enabled']) || $pderivative['must_enable'];

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
                $prev_w = 0;
                $prev_h = 0;
                foreach (ImageSizeType::getAllTypes() as $imageType) {
                    $pderivative = $pderivatives[$imageType->value];
                    if (!$pderivative['enabled']) {
                        continue;
                    }

                    if ($imageType === ImageSizeType::THUMB) {
                        $w = intval($pderivative['w']);
                        if ($w <= 0) {
                            $errors[$imageType->value]['w'] = '>0';
                        }

                        $h = intval($pderivative['h']);
                        if ($h <= 0) {
                            $errors[$imageType->value]['h'] = '>0';
                        }

                        if (max($w, $h) <= $prev_w) {
                            $errors[$imageType->value]['w'] = $errors[$imageType->value]['h'] = '>' . $prev_w;
                        }
                    } else {
                        $v = intval($pderivative['w']);
                        if ($v <= 0 || $v <= $prev_w) {
                            $errors[$imageType->value]['w'] = '>' . $prev_w;
                        }

                        $v = intval($pderivative['h']);
                        if ($v <= 0 || $v <= $prev_h) {
                            $errors[$imageType->value]['h'] = '>' . $prev_h;
                        }
                    }

                    if ($errors === []) {
                        $prev_w = intval($pderivative['w']);
                        $prev_h = intval($pderivative['h']);
                    }
                }

                // step 3 - save data
                if ($errors === []) {
                    $quality_changed = $image_std_params->getQuality() !== intval($request->request->get('resize_quality'));
                    $image_std_params->setQuality(intval($request->request->get('resize_quality')));

                    $enabled = $image_std_params->getDefinedTypeMap();
                    $disabled = empty($conf['disabled_derivatives']) ? [] : $conf['disabled_derivatives'];
                    $changed_types = [];

                    foreach (ImageSizeType::getAllTypes() as $imageType) {
                        $pderivative = $pderivatives[$imageType->value];

                        if ($pderivative['enabled']) {
                            $derivative_params = new SizingParams(
                                [intval($pderivative['w']), intval($pderivative['h'])],
                                round($pderivative['crop'] / 100, 2),
                                [intval($pderivative['minw']), intval($pderivative['minh'])]
                            );
                            $new_params = new DerivativeParams($derivative_params);
                            $image_std_params->applyWatermark($new_params);
                            if (isset($enabled[$imageType->value])) {
                                $old_params = $enabled[$imageType->value];
                                $same = true;
                                if (!DerivativeParams::sizeEquals($old_params->getSizing()->getIdealSize(), $new_params->getSizing()->getIdealSize())
                                    || $old_params->getSizing()->getMaxCrop() != $new_params->getSizing()->getMaxCrop()) {
                                    $same = false;
                                }

                                if ($same && $new_params->getSizing()->getMaxCrop() != 0
                                        && !DerivativeParams::sizeEquals($old_params->getSizing()->getMinSize(), $new_params->getSizing()->getMinSize())) {
                                    $same = false;
                                }

                                if (!$same) {
                                    $new_params->last_mod_time = time();
                                    $changed_types[] = $imageType;
                                } else {
                                    $new_params->last_mod_time = $old_params->last_mod_time;
                                }

                                $enabled[$imageType->value] = $new_params;
                            } else { // now enabled, before was disabled
                                $enabled[$imageType->value] = $new_params;
                                unset($disabled[$imageType->value]);
                            }
                        } elseif (isset($enabled[$imageType->value])) {
                            // disabled
                            // now disabled, before was enabled
                            $changed_types[] = $imageType;
                            $disabled[$imageType->value] = $enabled[$imageType->value];
                            unset($enabled[$imageType->value]);
                        }
                    }

                    $enabled_by = []; // keys ordered by all types
                    foreach (ImageSizeType::getAllTypes() as $imageType) {
                        if (isset($enabled[$imageType->value])) {
                            $enabled_by[$imageType->value] = $enabled[$imageType->value];
                        }
                    }

                    foreach (array_keys($image_std_params->getCustoms()) as $custom) {
                        if ($request->request->get('delete_custom_derivative_' . $custom)) {
                            $changed_types[] = $custom;
                            $image_std_params->unsetCustom($custom);
                        }
                    }

                    $image_std_params->setAndSave($enabled_by);
                    $conf->addOrUpdateParam('disabled_derivatives', $disabled, ConfEnum::BASE64);

                    if ($changed_types !== []) {
                        $derivativeService->clearCache($changed_types, ImageSizeType::getAllTypes());
                    }
                }
            }

            if ($conf_updated && !$error) {
                $this->addFlash('success', $this->translator->trans('Your configuration settings have been saved', [], 'admin'));
            }
        }

        return $this->redirectToRoute('admin_configuration', ['section' => $section]);
    }

    /**
     * @TODO: use symfony form
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getUploadFormConfig(): array
    {
        return [
            'original_resize' => [
                'default' => false,
                'can_be_null' => false,
            ],

            'original_resize_maxwidth' => [
                'default' => 2000,
                'min' => 500,
                'max' => 20000,
                'pattern' => '/^\d+$/',
                'can_be_null' => false,
                'error_message' => 'The original maximum width must be a number between %d and %d',
            ],

            'original_resize_maxheight' => [
                'default' => 2000,
                'min' => 300,
                'max' => 20000,
                'pattern' => '/^\d+$/',
                'can_be_null' => false,
                'error_message' => 'The original maximum height must be a number between %d and %d',
            ],

            'original_resize_quality' => [
                'default' => 95,
                'min' => 50,
                'max' => 98,
                'pattern' => '/^\d+$/',
                'can_be_null' => false,
                'error_message' => 'The original image quality must be a number between %d and %d',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<mixed> $errors
     * @param array<string, mixed> $form_errors
     *
     * @return array<array<string, mixed>>
     */
    public static function saveUploadFormConfig(array $data, array &$errors = [], array &$form_errors = []): array
    {
        $upload_form_config = self::getUploadFormConfig();
        $updates = [];

        foreach ($data as $field => $value) {
            if (!isset($upload_form_config[$field])) {
                continue;
            }

            if (is_bool($upload_form_config[$field]['default'])) {
                $value = isset($value);
                $updates[] = [
                    'param' => $field,
                    'value' => true
                ];
            } elseif ($upload_form_config[$field]['can_be_null'] && empty($value)) {
                $updates[] = [
                    'param' => $field,
                    'value' => null,
                ];
            } else {
                $min = $upload_form_config[$field]['min'];
                $max = $upload_form_config[$field]['max'];
                $pattern = $upload_form_config[$field]['pattern'];

                if (preg_match($pattern, (string) $value) && $value >= $min && $value <= $max) {
                    $updates[] = [
                        'param' => $field,
                        'value' => $value
                    ];
                } else {
                    $errors[] = sprintf(
                        $upload_form_config[$field]['error_message'],
                        $min,
                        $max
                    );

                    $form_errors[$field] = '[' . $min . ' .. ' . $max . ']';
                }
            }
        }

        if (count($errors) == 0) {
            return $updates;
        }

        return [];
    }
}

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

// provides data for site synchronization from the local file system

namespace Phyxo;

class LocalSiteReader
{
    var $site_url;

    public function __construct($url) {
        global $conf;

        $this->site_url = $url;

        if (!isset($conf['flip_file_ext'])) {
            $conf['flip_file_ext'] = array_flip($conf['file_ext']);
        }
        if (!isset($conf['flip_picture_ext'])) {
            $conf['flip_picture_ext'] = array_flip($conf['picture_ext']);
        }
    }

    /**
     * Is this local site ok ?
     *
     * @return true on success, false otherwise
     */
    public function open() {
        global $errors;

        if (!is_dir($this->site_url)) {
            $errors[] = array(
                'path' => $this->site_url,
                'type' => 'PWG-ERROR-NO-FS'
            );

            return false;
        }

        return true;
    }

    // retrieve file system sub-directories fulldirs
    public function get_full_directories($basedir) {
        $fs_fulldirs = get_fs_directories($basedir);
        return $fs_fulldirs;
    }

    /**
     * Returns an array with all file system files according to $conf['file_ext']
     * and $conf['picture_ext']
     * @param string $path recurse in this directory
     * @return array like "pic.jpg"=>array('representative_ext'=>'jpg' ... )
     */
    public function get_elements($path) {
        global $conf;

        $subdirs = array();
        $fs = array();
        if (is_dir($path) && $contents = opendir($path) ) {
            while (($node = readdir($contents)) !== false) {
                if ($node == '.' or $node == '..') { continue;}

                if (is_file($path.'/'.$node)) {
                    $extension = get_extension($node);
                    $filename_wo_ext = get_filename_wo_extension($node);

                    if (isset($conf['flip_file_ext'][$extension])) {
                        $representative_ext = null;
                        if (!isset($conf['flip_picture_ext'][$extension])) {
                            $representative_ext = $this->get_representative_ext($path, $filename_wo_ext);
                        }
                        $fs[ $path.'/'.$node ] = array('representative_ext' => $representative_ext);
                    }
                } elseif (is_dir($path.'/'.$node) && $node != 'pwg_high' && $node != 'pwg_representative' && $node != 'thumbnail' ) {
                    $subdirs[] = $node;
                }
            } //end while readdir
            closedir($contents);

            foreach ($subdirs as $subdir) {
                $tmp_fs = $this->get_elements($path.'/'.$subdir);
                $fs = array_merge($fs, $tmp_fs);
            }
            ksort($fs);
        } //end if is_dir
        return $fs;
    }

    // returns the name of the attributes that are supported for
    // files update/synchronization
    public function get_update_attributes() {
        return array('representative_ext');
    }

    public function get_element_update_attributes($file) {
        global $conf;
        $data = array();

        $filename = basename($file);
        $extension = get_extension($filename);

        $representative_ext = null;
        if (!isset($conf['flip_picture_ext'][$extension])) {
            $dirname = dirname($file);
            $filename_wo_ext = get_filename_wo_extension($filename);
            $representative_ext = $this->get_representative_ext($dirname, $filename_wo_ext);
        }

        $data['representative_ext'] = $representative_ext;
        return $data;
    }

    // returns the name of the attributes that are supported for
    // metadata update/synchronization according to configuration
    public function get_metadata_attributes() {
        return get_sync_metadata_attributes();
    }

    // returns a hash of attributes (metadata+filesize+width,...) for file
    public function get_element_metadata($infos) {
        return get_sync_metadata($infos);
    }

    //-------------------------------------------------- private functions --------
    public function get_representative_ext($path, $filename_wo_ext) {
        global $conf;
        $base_test = $path.'/pwg_representative/'.$filename_wo_ext.'.';
        foreach ($conf['picture_ext'] as $ext) {
            $test = $base_test.$ext;
            if (is_file($test)) {
                return $ext;
            }
        }
        return null;
    }
}

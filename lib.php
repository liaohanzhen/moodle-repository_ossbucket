<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This plugin is used to access ossbucket files
 *
 * @package    repository_ossbucket
 * @copyright  2021 Martin Liao (liaohanzhen@163.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/repository/lib.php');
// require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');
require_once($CFG->dirroot . '/local/aliyunoss/sdk/autoload.php');
use OSS\OssClient;
use OSS\Core\OssException;

/**
 * This is a repository class used to browse a Amazon S3 bucket.
 *
 * @package    repository_ossbucket
 * @copyright  2017 Renaat Debleu (www.eWallah.net) (based on work by Dongsheng Cai)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_ossbucket extends repository {

    /** @var _ossclient oss client object */
    private $_ossclient;

    /**
     * Extracts the Bucket and URI from the path
     *
     * @param string $path path in this format 'bucket/path/to/folder/and/file'
     * @return array including bucket and uri
     */
    protected function explode_path($path) {
        $parts = explode('/', $path, 2);
        
        if (isset($parts[1]) && $parts[1] !== '') {
            list($bucket, $uri) = $parts;
        } else {
            $bucket = $parts[0];
            $uri = '';
        }
        return array($bucket, $uri);
    }

    /**
     * Get OSS file list
     *
     * @param string $path this parameter can a folder name, or a identification of folder
     * @param string $page the page number of file list
     * @return array the list of files, including some meta infomation
     */
    public function get_listing($path = '.', $page = 1) {
        global $OUTPUT;
        $diricon = $OUTPUT->image_url(file_folder_icon(64))->out(false);
        $bucket = $this->get_option('bucket_name');
        $place = [['name' => $bucket, 'path' => $path]];
        $epath = ($path === '') ? '.' : $path . '/';
        $options = array(
            'delimiter' => '/',
            'prefix' => $path,
            'max-keys' => 1000,
            'marker' => '',
        );
        $results = $files = [];
        $ossClient = $this->create_oss();
        try {
            $results = $ossClient->listObjects($bucket, $options);
        }
        catch (OssException $e) {
            throw new moodle_exception('errorwhilecommunicatingwith', 'repository', '', $this->get_name(), $e->getMessage());
        }

        $prefixList = $results->getPrefixList(); // directory list
        $objectList = $results->getObjectList(); // object list
        setlocale(LC_ALL, 'zh_CN.GBK'); // 针对中文不能取到文件名 ,设置本地化  
        foreach ($prefixList as $prefixInfo) {
            $prefixPath = $prefixInfo->getPrefix();
            $prefixName = basename($prefixPath); 
            // $prefixName = preg_replace('/^.+[\\\\\\/]/', '', $prefixPath);  //中文路径文件名的另一种方案
            $files[] = [
                'title' => $prefixName,
                'children' => [],
                'thumbnail' => $diricon,
                'thumbnail_height' => 64,
                'thumbnail_width' => 64,
                'path' => $prefixPath];
        }

        foreach ($objectList as $objectInfo) {
            $objectPath = $objectInfo->getKey();
            if(substr($objectPath, -1) == '/') continue;  // 如果是路径，则忽略
            $pathinfo = pathinfo($objectPath);
            if($pathinfo['extension'] == 'ts') continue; // 去掉后缀为ts的文件
            $files[] = [
                'title' => $pathinfo['basename'], // 原始值为url编码值
                'size' => $objectInfo->getSize() ,
                'path' => $objectPath ,
                'datemodified' => $objectInfo->getLastModified(),
                'thumbnail_height' => 64,
                'thumbnail_width' => 64,
                'source' => $objectPath, // 原始值为url编码值
                'thumbnail' => $OUTPUT->image_url(file_extension_icon($pathinfo['basename'], 64))->out(false)];
        }
        
        return [
           'list' => $files,
           'path' => $place,
           'manage' => false,
           'dynload' => true,
           'nologin' => true,
           'nosearch' => false];
    }

    /**
     * Search through all the files.
     *
     * @param  String  $q    The query string.
     * @param  integer $page The page number.
     * @return array of results.
     */
    public function search($q, $page = 1) {
        global $OUTPUT;
        $diricon = $OUTPUT->image_url(file_folder_icon(64))->out(false);
        $bucket = $this->get_option('bucket_name');
        // $options = [
        //     'Bucket' => $bucket, // kltomo
        //     'FetchOwner' => false,
        //     'MaxKeys' => 1000,
        //     'EncodingType' => 'url',
        //     'Delimiter' => '/'];
        $options = array(
            'delimiter' => '/',
            'max-keys' => 1000,
            'marker' => '',
        );
        
        $results = $files = [];
        $ossClient = $this->create_oss();
        try {
            $results = $ossClient->listObjects($bucket, $options);
        }
        catch (OssException $e) {
            throw new moodle_exception('errorwhilecommunicatingwith', 'repository', '', $this->get_name(), $e->getMessage());
        }
        $objectList = $results->getObjectList();
        $prefixList = $results->getPrefixList();
     
        $dirsearch = 'CommonPrefixes[?contains(Prefix, \'' . $q . '\')].{Prefix: Prefix}';
        foreach ($prefixList as $prefixInfo) {
            $files[] = [
                'title' => basename($prefixInfo->getPrefix()),
                'children' => [],
                'thumbnail' => $diricon,
                'thumbnail_height' => 64,
                'thumbnail_width' => 64,
                'path' => $prefixInfo->getPrefix()];
        }

        var_dump($objectList);
        foreach ($objectList as $objectInfo) {
            
            $pathinfo = pathinfo($objectInfo->getKey());
            $files[] = [
                'title' => $pathinfo['basename'], // 原始值为url编码值
                'size' => $objectInfo->getSize() ,
                'path' => $objectInfo->getKey() ,
                'datemodified' => $objectInfo->getLastModified(),
                'thumbnail_height' => 64,
                'thumbnail_width' => 64,
                'source' => $objectInfo->getKey(), // 原始值为url编码值
                'thumbnail' => $OUTPUT->image_url(file_extension_icon($pathinfo['basename'], 64))->out(false)];
        }
        
        // foreach ($results->search($dirsearch) as $item) {            
        //      $files[] = [
        //           'title' => basename($item['Prefix']),
        //           'children' => [],
        //           'thumbnail' => $diricon,
        //           'thumbnail_height' => 64,
        //           'thumbnail_width' => 64,
        //           'path' => $item['Prefix']];
        // }

        // $filesearch = 'Contents[?StorageClass != \'DEEP_ARCHIVE\'';
        // $filesearch .= ' && StorageClass != \'GLACIER\'';
        // $filesearch .= ' && contains(Key, \'' . $q . '\')]';
        // $filesearch .= '.{Key: Key, Size: Size, LastModified: LastModified}';
        // foreach ($results->search($filesearch) as $item) {
        //     $pathinfo = pathinfo($item['Key']);
        //     $files[] = [
        //        'title' => $pathinfo['basename'],
        //        'size' => $item['Size'],
        //        'path' => $item['Key'],
        //        'datemodified' => date_timestamp_get($item['LastModified']),
        //        'thumbnail_height' => 64,
        //        'thumbnail_width' => 64,
        //        'source' => $item['Key'],
        //        'thumbnail' => $OUTPUT->image_url(file_extension_icon($pathinfo['basename'], 64))->out(false)];
        // }
        return ['list' => $files, 'dynload' => true, 'pages' => 0, 'page' => $page];
    }

    /**
     * Repository method to serve the referenced file
     * 获得别名/参考文件
     *
     * @param stored_file $storedfile the file that contains the reference
     * @param int $lifetime Number of seconds before the file should expire from caches (null means $CFG->filelifetime)
     * @param int $filter 0 (default)=no filtering, 1=all files, 2=html files only
     * @param bool $forcedownload If true (default true), forces download of file rather than view in browser/plugin
     * @param array $options additional options affecting the file serving
     */
    public function send_file($storedfile, $lifetime = null, $filter = 0, $forcedownload = true, array $options = null) {
        $duration = get_config('ossbucket', 'duration');
        $this->send_otherfile($storedfile->get_reference(), "+$duration minutes");
    }

    /**
     * Repository method to serve the out file
     *
     * @param string $reference the filereference
     * @param string $lifetime Number of seconds before the file should expire from caches
     */
    public function send_otherfile($reference, $lifetime) {
        if ($reference != '') {
            $oss = $this->create_oss();
            
            try {
                // 生成签名URL。
                $signedUrl = $oss->signUrl($this->get_option('bucket_name'), $reference, 3600);

                // 如果需要获得m3u8视频
                if($this->get_option('use_m3u8_bucket')){
                    $replaceBucket = $this->get_option('m3u8_bucket');
                    // $reference = preg_replace('#.$#i', '.m3u3', $reference);
                    $extension = pathinfo($reference,PATHINFO_EXTENSION);
                    if ($extension='mp4'){
                        $t1 = microtime(true);
                        $referenceReplace = str_replace($extension , 'm3u8', $reference); 
                        $signedUrlReplace = $oss->signUrl($replaceBucket, $referenceReplace, 3600);  
                        debugging('url:'.$signedUrlReplace);                 
                        $array = get_headers($signedUrlReplace,1);
                        if(preg_match('/200/',$array[0])){
                            $signedUrl = $signedUrlReplace;
                            debugging('url有效');
                        }else{
                            debugging('url无效');
                        }
                        $t2 = microtime(true);
                        debugging('耗时'.round($t2-$t1,3).'秒');
                    }
                }              
                
                                
            } catch (Exception $e) {
                throw new moodle_exception('errorwhilecommunicatingwith', 'repository', '', $this->get_name(), $e->getMessage());
            }
            $uri = $signedUrl;
            header("Location: $uri");
            
            die;
        }
        throw new repository_exception('cannotdownload', 'repository');
    }

    /**
     * Get human readable file info from a the reference.
     * 从别名/参考文件中获得可读的文件信息
     *
     * @param string $reference
     * @param int $filestatus 0 - ok, 666 - source missing
     */
    public function get_reference_details($reference, $filestatus = 0) {
        if ($this->disabled) {
            throw new repository_exception('cannotdownload', 'repository');
        }
        if ($filestatus == 666) {
            $reference = '';
        }
        return $this->get_file_source_info($reference);
    }

    /**
     * Download OSS files to moodle
     *
     * @param string $filepath
     * @param string $file The file path in moodle
     * @return array with elements:
     *   path: internal location of the file
     *   url: URL to the source (from parameters)
     */
    public function get_file($filepath, $file = '') {
        $path = $this->prepare_file($file);
        $oss = $this->create_oss();
        $options = array(
            OssClient::OSS_FILE_DOWNLOAD => $path,
        );
    
        try {
            $oss->getObject($this->get_option('bucket_name'), $filepath, $options);
        } catch (OssException $e) {
            throw new moodle_exception('errorwhilecommunicatingwith', 'repository', '', $this->get_name(), $e->getMessage());
        }

        return ['path' => $path, 'url' => $filepath];
    }

    /**
     * Return the source information
     * 返回源信息
     *
     * @param stdClass $filepath
     * @return string
     */
    public function get_file_source_info($filepath) {
        if (empty($filepath) or $filepath == '') {
            return get_string('unknownsource', 'repository');
        }
        return $this->get_short_filename('oss://' . $this->get_option('bucket_name') . '/' . $filepath, 50);
    }

    /**
     * Return names of the general options.
     *
     * @return array
     */
    public static function get_type_option_names() {
        return ['duration'];
    }

    /**
     * Edit/Create Admin Settings Moodle form
     * 编辑/创建管理设置的表单
     *
     * @param moodleform $mform Moodle form (passed by reference)
     * @param string $classname repository class name
     */
    public static function type_config_form($mform, $classname = 'repository') {
        $duration = get_config('ossbucket', 'duration') ?? 2;
        $choices = ['1' => 1, '2' => 2, '10' => 10, '15' => 15, '30' => 30, '60' => 60];
        $mform->addElement('select', 'duration', get_string('duration', $classname), $choices, $duration);
        $mform->setType('duration', PARAM_INT);
    }

    /**
     * Return names of the instance options.
     * 返回容器实例名称
     * By default: no instance option name
     *
     * @return array
     */
    public static function get_instance_option_names() {
        return ['access_key', 'secret_key', 'endpoint', 'bucket_name', 'use_m3u8_bucket', 'm3u8_bucket'];
    }

    /**
     * Edit/Create Instance Settings Moodle form
     * 容器实例编辑/创建的表单
     *
     * @param moodleform $mform Moodle form (passed by reference)
     */
    public static function instance_config_form($mform) {
        global $CFG;
        parent::instance_config_form($mform);
        $strrequired = get_string('required');
        $textops = ['maxlength' => 255, 'size' => 50];
        $endpointselect = [];
        $all = require($CFG->dirroot . '/local/aliyunoss/sdk/data/endpoints.json.php');
        $endpoints = $all['partitions'][0]['regions'];
        foreach ($endpoints as $key => $value) {
            $endpointselect[$key] = $value['description'];
        }

        $mform->addElement('passwordunmask', 'access_key', get_string('access_key', 'repository_ossbucket'), $textops);
        $mform->setType('access_key', PARAM_RAW_TRIMMED);
        $mform->addElement('passwordunmask', 'secret_key', get_string('secret_key', 'repository_ossbucket'), $textops);
        $mform->setType('secret_key', PARAM_RAW_TRIMMED);
        $mform->addElement('text', 'bucket_name', get_string('bucketname', 'repository_ossbucket'), $textops);
        $mform->setType('bucket_name', PARAM_RAW_TRIMMED);
        $mform->addElement('select', 'endpoint', get_string('endpoint', 'repository_ossbucket'), $endpointselect);
        $mform->setDefault('endpoint', 'oss-cn-beijing');

        $mform->addRule('access_key', $strrequired, 'required', null, 'client');
        $mform->addRule('secret_key', $strrequired, 'required', null, 'client');
        $mform->addRule('bucket_name', $strrequired, 'required', null, 'client');

        // 使用转换媒体bucket
        $mform->addElement('checkbox', 'use_m3u8_bucket',
                           '使用m3u8媒体Bucket');
        $mform->addElement('text', 'm3u8_bucket', 'm3u8所在Bucket', $textops);
        $mform->setDefault('m3u8_bucket', 'kltomo-temp');
        
    }

    /**
     * Validate repository plugin instance form
     * 容器实例表单填写校验
     *
     * @param moodleform $mform moodle form
     * @param array $data form data
     * @param array $errors errors
     * @return array errors
     */
    public static function instance_form_validation($mform, $data, $errors) {
        if (isset($data['access_key']) && isset($data['secret_key']) && isset($data['bucket_name'])) {
            $oss = new OssClient($data['access_key'], $data['secret_key'], 'https://'.$data['endpoint'].'.aliyuncs.com', false); 
            try {
                // Check if the bucket exists.
                $oss->getBucketAcl($data['bucket_name']);
                // $oss->getCommand('HeadBucket', ['Bucket' => $data['bucket_name']]);
            } catch (Exception $e) {
                $errors[] = get_string('errorwhilecommunicatingwith', 'repository');
            }
        }
        return $errors;
    }

    /**
     * Which return type should be selected by default.
     *
     * @return int
     */
    public function default_returntype() {
        return FILE_REFERENCE;
    }

    /**
     * S3 plugins does support return links of files
     *
     * @return int
     */
    public function supported_returntypes() {
        return FILE_INTERNAL | FILE_REFERENCE | FILE_EXTERNAL;
    }

    /**
     * Get OSS
     *
     * @return oss
     */
    private function create_oss() {
        if ($this->_ossclient == null) {
            $accesskey = $this->get_option('access_key');
            if (empty($accesskey)) {
                throw new moodle_exception('needaccesskey', 'repository_ossbucket');
            }
            try {
                $ossClient = new OssClient($accesskey, $this->get_option('secret_key'), 'https://'.$this->get_option('endpoint').'.aliyuncs.com', false);                
            } catch (OssException $e) {
                debugging("Warning: creating OssClient instance: FAILED".$e->getMessage());
                return null;
            }

            return $ossClient;
        }
        return $this->_ossclient;
    }

    /**
     * Is this repository accessing private data?
     *
     * This function should return false to give access to private repository data.
     * @return boolean True when the repository accesses private external data.
     */
    public function contains_private_data() {
        return ($this->context->contextlevel === CONTEXT_USER);
    }
}

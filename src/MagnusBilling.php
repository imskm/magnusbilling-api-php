<?php
/**
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2005 - 2022 MagnusSolution. All rights reserved.
 * ###################################
 *
 * This software is released under the terms of the GNU Lesser General Public License v2.1
 * A copy of which is available from http://www.gnu.org/copyleft/lesser.html
 *
 * Please submit bug reports, patches, etc to https://github.com/magnusbilling/mbilling/issues
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 *
 */

namespace magnusbilling\api;

class MagnusBilling
{
    protected $api_key;
    protected $api_secret;
    public $public_url;
    public $filter = [];
    public $columns = [];

    public function __construct($api_key, $api_secret)
    {
        $this->api_key    = $api_key;
        $this->api_secret = $api_secret;
    }

    private function query(array $req = array())
    {

        // API settings
        $key         = $this->api_key;
        $secret      = $this->api_secret;
        $trading_url = $this->public_url;

        $module = $req['module'];
        $action = $req['action'];

        // generate a nonce to avoid problems with 32bit systems
        $mt           = explode(' ', microtime());
        $req['nonce'] = $mt[1] . substr($mt[0], 2, 6);

        // generate the POST data string
        $post_data = http_build_query($req, '', '&');
        $sign      = hash_hmac('sha512', $post_data, $secret);

        // generate the extra headers
        $headers = array(
            'Key: ' . $key,
            'Sign: ' . $sign,
        );

        // curl handle (initialize if required)
        static $ch = null;
        if (is_null($ch)) {
            $ch = curl_init();
            if (isset($req['write_cb'])) {
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, $req['write_cb']);
            } else {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            }
            curl_setopt($ch, CURLOPT_USERAGENT,
                'Mozilla/4.0 (compatible; MagnusBilling PHP bot; ' . php_uname('a') . '; PHP/' . phpversion() . ')'
            );
        } else {
            if (isset($req['write_cb'])) {
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, $req['write_cb']);
            } else {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            }
        }
        curl_setopt($ch, CURLOPT_URL, $trading_url . '/index.php/' . $module . '/' . $action);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // run the query
        $res = curl_exec($ch);

        if ($res === false) {
            throw new \Exception('Curl error: ' . curl_error($ch));
        }

        if (isset($req['write_cb'])) {
            return $res;
        }

        $dec = json_decode($res, true);
        if (!$dec) {
            print_r($res);
            exit;
        } else {
            return $dec;
        }
    }

    public function create($module, $data = [], $action = 'save')
    {
        $data['module'] = $module;
        $data['action'] = $action;
        $data['id']     = 0;

        return $this->query($data);
    }

    public function update($module, $id, $data)
    {
        $data['module'] = $module;
        $data['action'] = 'save';
        $data['id']     = $id;

        return $this->query($data);
    }

    public function destroy($module, $id)
    {
        return $this->query(
            array(
                'module' => $module,
                'action' => 'destroy',
                'id'     => $id,
            )
        );
    }
    public function read($module, $page = 1, $action = 'read', $limit = 25)
    {
        $query = [
            'module' => $module,
            'action' => $action,
            'page'   => $page,
            'start'  => $page == 1 ? 0 : ($page - 1) * $limit,
            'limit'  => $limit,
            'filter' => json_encode($this->filter),
        ];
        if (isset($this->columns[0])) {
            $query['columns'] = json_encode($this->columns);
        }

        return $this->query($query);
    }

    public function buyDID($id_did, $id_user)
    {

        return $this->query(
            array(
                'module'  => 'did',
                'action'  => 'buy',
                'id'      => $id_did,
                'id_user' => $id_user,
            )
        );
    }

    public function spyCall($channel, $sip_account, $type)
    {

        return $this->query(
            [
                'module'  => 'callOnLine',
                'action'  => 'spyCall',
                'channel' => $channel,
                'sipuser' => $sip_account,
                'type'    => $type,
            ]
        );
    }

    public function releaseDID($did)
    {
        $this->setFilter('did', $did, 'eq', 'string');

        $result = $this->read('did');

        if (!isset($result['rows'][0]['id'])) {
            return json_encode(['error' => 'DID ' . $did . ' not exist']);
        }

        $this->clearFilter();

        return $this->query(
            array(
                'module' => 'did',
                'action' => 'liberar',
                'ids'    => json_encode([$result['rows'][0]['id']]),
            )
        );
    }

    public function getCallAudioRecording($call_id, $stream_cb)
    {
        $this->clearFilter();
        $this->setFilter('id', $call_id, 'eq', 'numeric');

        return $this->query(
            array(
                'module' => "call",
                'action' => "downloadRecordTwo",
                'page'   => $page = 1,
                'start'  => $page == 1 ? 0 : ($page - 1) * $limit,
                'limit'  => $limit = 25,
                'filter' => json_encode($this->filter),
                'write_cb' => $stream_cb,
            )
        );
    }

    public function downloadResource($module, $action, $limit, $stream_cb)
    {
        $query = [
            'module' => $module,
            'action' => $action,
            'page'   => $page = 1,
            'start'  => $page == 1 ? 0 : ($page - 1) * $limit,
            'limit'  => $limit,
            'filter' => json_encode($this->filter),
            'write_cb' => $stream_cb,
        ];
        if (isset($this->columns[0])) {
            $query['columns'] = json_encode($this->columns);
        }

        return $this->query($query);
    }

    public function getFields($module)
    {
        return $this->query(
            array(
                'module'    => $module,
                'getFields' => 1,
            )
        );
    }

    public function createUser($data)
    {
        $data['module']     = 'user';
        $data['action']     = 'save';
        $data['createUser'] = 1;
        $data['id']         = 0;

        return $this->query($data);
    }

    public function getModules()
    {
        return $this->query(
            array(
                'getModules' => 1,
            )
        );
    }

    public function getMenu($username)
    {
        return $this->query(
            array(
                'username' => $username,
                'getMenu'  => 1,
            )
        );
    }

    public function getId($module, $filed, $value)
    {

        $this->setFilter($filed, $value, 'eq');

        $query = $this->query([
            'module' => $module,
            'action' => 'read',
            'page'   => 1,
            'start'  => 0,
            'limit'  => 1,
            'filter' => json_encode($this->filter),
        ]);

        $this->filter = [];

        if (isset($query['rows'][0])) {
            return $query['rows'][0]['id'];
        }
    }

    public function clearFilter()
    {
        $this->filter = [];
    }

    public function setFilter($field, $value, $comparison = 'st', $type = 'string')
    {
        $this->filter[] = (object) [
            'type'       => $type,
            'field'      => $field,
            'value'      => $value,
            'comparison' => $comparison,
        ];
    }

    public function setColumn($header, $field)
    {
        $this->columns[] = (object) [
            'header' => $header,
            'dataIndex' => $field,
        ];
    }
}

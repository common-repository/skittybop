<?php

// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('SkittybopAjax')) :

    /**
     * Handles all server side business logic for ajax requests.
     */
    class SkittybopAjax
    {

        public function __construct()
        {
            add_action('wp_ajax_skittybop_fetch_operators', array($this, 'fetch_operators'));
            add_action('wp_ajax_skittybop_call', array($this, 'start_call'));
            add_action('wp_ajax_skittybop_fetch_calls', array($this, 'fetch_history'));
            add_action('wp_ajax_skittybop_delete_calls', array($this, 'delete_calls'));
            add_action('wp_ajax_skittybop_change_call_status', array($this, 'change_call_status'));
            add_action('wp_ajax_skittybop_change_call_timestamp', array($this, 'change_call_timestamp'));

            add_filter('heartbeat_received', array($this, 'skittybop_receive_heartbeat'), 10, 2);
        }

        public function fetch_operators()
        {
            check_ajax_referer('skittybop-fetch-operators');

            $query = new WP_User_Query(array(
                'role' => SkittybopRole::OPERATOR,
                'fields' => 'all_with_meta',
                'orderby' => 'user_login',
                'order' => 'ASC'
            ));
            $operators = $query->get_results();

            $data = array();
            foreach ($operators as &$operator) {
                $online = $operator->get(SkittybopOption::ONLINE);
                $data[] = array(
                    "responsive" => '',
                    "user_id" => $operator->data->ID,
                    "user_name" => $operator->data->display_name,
                    "user_status" => isset($online) && $online === '1'
                );
            }

            die(wp_json_encode(array("data" => $data)));
        }

        public function start_call()
        {
            check_ajax_referer('skittybop-call');

            $query = new WP_User_Query(array(
                'fields' => array('ID'),
                'role' => SkittybopRole::OPERATOR,
                'meta_key' => SkittybopOption::ONLINE,
                'meta_value' => '1',
                'exclude' => array(get_current_user_id())
            ));
            $operators = $query->get_results();

            if (empty($operators)) {
                die(wp_json_encode(array("error" => "no_operator")));
            }

            global $wpdb;
            $room = skittybop_generate_unique_room();
            $user_id = get_current_user_id();
            foreach ($operators as $operator) {
                $data = array(
                    "room" => $room,
                    "user_id" => $user_id,
                    "operator_id" => $operator->ID
                );
                $wpdb->insert($wpdb->prefix . SKITTYBOP_TABLE_CALLS, $data);
            }

            die(wp_json_encode(array("room" => $room)));
        }

        public function fetch_history()
        {
            check_ajax_referer('skittybop-fetch-calls');

            global $wpdb;

            if (current_user_can(SkittybopRole::ADMINISTRATOR)) {
                $columns = array(
                    0 => 'responsive',
                    1 => 'select',
                    2 => 'id',
                    3 => 'operator_id',
                    4 => 'status',
                    5 => 'room',
                    6 => 'started_at',
                    7 => 'ended_at',
                );
            } else {
                $columns = array(
                    0 => 'responsive',
                    1 => 'id',
                    2 => 'operator_id',
                    3 => 'status',
                    4 => 'room',
                    5 => 'started_at',
                    6 => 'ended_at',
                );
            }

            $draw = isset($_REQUEST['draw']) ? intval($_REQUEST['draw']) : 1;
            $start = isset($_REQUEST['start']) ? intval($_REQUEST['start']) : 0;
            $length = isset($_REQUEST['length']) ? intval($_REQUEST['length']) : 10;
            $from_date = isset($_REQUEST['from_date']) ? sanitize_text_field(wp_unslash($_REQUEST['from_date'])) : null;
            $to_date = isset($_REQUEST['to_date']) ? sanitize_text_field(wp_unslash($_REQUEST['to_date'])) : null;
            $search = isset($_REQUEST['search']) ? array_map('sanitize_text_field', wp_unslash($_REQUEST['search'])) : array();
            $order = isset($_REQUEST['order'][0]) ? array_map('sanitize_text_field', wp_unslash($_REQUEST['order'][0])) : array();

            $calls_table = $wpdb->prefix . SKITTYBOP_TABLE_CALLS;
            $users_table = $wpdb->prefix . 'users';
            $usersmeta_table = $wpdb->prefix . 'usermeta';

            $select = "
                SELECT DATE_FORMAT(CONVERT_TZ(c.started_at, @@session.time_zone, '+00:00'), %s) as started_iso,
                       DATE_FORMAT(CONVERT_TZ(c.ended_at, @@session.time_zone, '+00:00'), %s) as ended_iso,
                       u.display_name, m.meta_value, c.*
                FROM %i AS c JOIN %i AS u ON c.operator_id = u.ID LEFT JOIN %i AS m ON c.operator_id = m.user_id AND m.meta_key = %s
            ";

            // Build additional SQL components
            $limit = self::limit($start, $length);
            $order = self::order($order, $columns);
            $whereBindings = array();
            $where = self::filter($search, $from_date, $to_date, array(0 => 'id', 1 => 'display_name', 2 => 'room', 3 => 'status'), $whereBindings);

            $query = $select . $where . $order . $limit;
            $iso8601_format = '%Y-%m-%dT%TZ';
            $bindings = array_merge(array($iso8601_format, $iso8601_format, $calls_table, $users_table, $usersmeta_table, SkittybopOption::ONLINE), $whereBindings);
            $calls = $wpdb->get_results(
                $wpdb->prepare($query, ...$bindings) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            );
            if (!isset($calls) or empty($calls)) {
                die(wp_json_encode(array(
                    "draw" => $draw,
                    "recordsTotal" => 0,
                    "recordsFiltered" => 0,
                    "data" => array()
                )));
            }
            $data = array();
            foreach ($calls as &$call) {
                $data[] = array(
                    "id" => $call->id,
                    "operator" => $call->display_name,
                    "operator_status" => isset($call->meta_value) && $call->meta_value === '1',
                    "room" => $call->room,
                    "status" => $call->status,
                    "started" => $call->started_iso,
                    "ended" => $call->ended_iso,
                    "responsive" => '',
                    "select" => '',
                );
            }

            $select = "SELECT COUNT('id') AS count FROM %i AS c JOIN %i as u ON (c.operator_id = u.ID)";
            $bindings = array_merge(array($calls_table, $users_table), $whereBindings);

            $count = $wpdb->get_results(
                $wpdb->prepare($select . $where, $bindings) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            );
            $recordsFiltered = isset($count[0]->count) ? $count[0]->count : 0;

            $response = array(
                "draw" => $draw,
                "recordsTotal" => $recordsFiltered,
                "recordsFiltered" => $recordsFiltered,
                "data" => $data
            );

            die(wp_json_encode($response));
        }

        public function change_call_status()
        {
            check_ajax_referer('skittybop-change-call-status');

            $status = isset($_REQUEST['status']) ? intval($_REQUEST['status']) : null;
            $room = isset($_REQUEST['room']) ? sanitize_text_field(wp_unslash($_REQUEST['room'])) : null;

            if (!isset($status) || !isset($room)) {
                die(wp_json_encode(null));
            }

            global $wpdb;
            $success = false;
            $response = null;
            try {
                $wpdb->query('START TRANSACTION');

                $callsTable = $wpdb->prefix . SKITTYBOP_TABLE_CALLS;

                //try to lock all pending calls with the provided room
                $calls = $wpdb->get_results(
                    $wpdb->prepare("SELECT status FROM %i WHERE room = %s AND status = %d FOR UPDATE SKIP LOCKED",
                        array($callsTable, $room, SkittybopCallStatus::PENDING)
                    )
                );

                //if you managed to get the lock, try to update the status
                if ($calls) {
                    //handle timeouts and client cancel
                    if ($status === SkittybopCallStatus::CANCELED || $status === SkittybopCallStatus::FAILED) {
                        $result = $wpdb->query($wpdb->prepare("UPDATE %i SET status = %d WHERE room = %s",
                            array($callsTable, $status, $room)));
                        $success = $result !== false && $result > 0;
                    } else {
                        //handle operator accept / reject statuses
                        $operatorId = get_current_user_id();
                        $result = $wpdb->query($wpdb->prepare("UPDATE %i SET status = CASE WHEN operator_id = %d THEN %d ELSE %d END WHERE room = %s",
                            array($callsTable, $operatorId, $status, SkittybopCallStatus::FAILED, $room)));
                        $success = $result !== false && $result > 0;
                    }
                }
                $wpdb->query('COMMIT');

                //if you managed to update the status inform the client that there is an incoming call
                if ($success && $status === SkittybopCallStatus::ACCEPTED) {
                    $resp = $this->requestToken($room);
                    $isError = is_numeric($resp) && $resp < 0;
                    if ($isError) {
                        $response = array("room" => $room, "jwt" => null, "error" => $resp);
                    }  else {
                        $response = array("room" => $room, "jwt" => $resp, "error" => 0);
                    }
                }
            } catch (Throwable $e) {
                $wpdb->query('ROLLBACK');
            }

            die(wp_json_encode($response));
        }

        public function change_call_timestamp()
        {
            check_ajax_referer('skittybop-change-call-timestamp');

            $room = isset($_REQUEST['room']) ? sanitize_text_field(wp_unslash($_REQUEST['room'])) : null;
            $start = isset($_REQUEST['start']) ? boolval($_REQUEST['start']) : null;
            $end = isset($_REQUEST['end']) ? boolval($_REQUEST['end']) : null;

            if (!isset($room) || (!$start && !$end)) {
                die(wp_json_encode(null));
            }

            global $wpdb;
            $response = null;
            try {
                $callsTable = $wpdb->prefix . SKITTYBOP_TABLE_CALLS;
                $user_id = get_current_user_id();
                $result = false;
                if ($start) {
                    $result = $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE %i SET started_at = now() WHERE room = %s AND started_at is NULL AND (user_id = %d OR operator_id = %d) AND status = %d",
                            array($callsTable, $room, $user_id, $user_id, SkittybopCallStatus::ACCEPTED)
                        )
                    );
                }
                if ($end) {
                    $result = $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE %i SET ended_at = now() WHERE room = %s AND ended_at is NULL AND (user_id = %d OR operator_id = %d) AND status = %d",
                            array($callsTable, $room, $user_id, $user_id, SkittybopCallStatus::ACCEPTED)
                        )
                    );
                }
                $success = $result !== false && $result > 0;
                $response = $success ? $room : null;
            } catch (Throwable $e) {
            }

            die(wp_json_encode($response));
        }

        public function delete_calls()
        {
            check_ajax_referer('skittybop-delete-calls');

            $calls = isset($_REQUEST['calls']) ? array_map('intval', $_REQUEST['calls']) : array();

            if (empty($calls)) {
                die(wp_json_encode(array("error" => "no_selected")));
            }

            if (!current_user_can(SkittybopRole::ADMINISTRATOR)) {
                die(wp_json_encode(array("error" => "no_permission")));
            }

            global $wpdb;
            $response = array();
            try {
                $callsTable = $wpdb->prefix . TABLE_CALLS;
                $whereArray = array();
                foreach ($calls as $call) {
                    $whereArray[] = "id = %d";
                }
                $where = implode(" OR ", $whereArray);
                array_unshift($calls, $callsTable);
                $result = $wpdb->query(
                    $wpdb->prepare("DELETE FROM %i WHERE " . $where, $calls) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                );
                $success = $result !== false && $result > 0;
                $response = $success ? array("count" => $result) : array("error" => "data_not_found");
            } catch (Throwable $e) {
            }

            die(wp_json_encode($response));
        }

        public function requestToken($room) {
            $url = SKITTYBOP_APP_API_URL . "/apps/generateToken";
            $apiKey = get_option('skittybop_api_key', null);
            $user = wp_get_current_user();

            if (!isset($url) || !isset($apiKey) || !isset($room) || !isset($user) || !isset($user->ID)) {
                return SkittybopErrorCodes::INVALID_REQUEST;
            }

            $headers = array(
                "Content-type" => "application/json",
                "Authorization" => "Bearer " . $apiKey
            );

            $data = array(
                'room' => $room,
                'userId' => strval($user->ID),
                'userName' => $user->display_name ?? $user->user_login,
                'userAvatar' => get_avatar_url($user->ID) ?? "",
                'userEmail' => $user->user_email ?? "",
                "isOperator" => current_user_can(SkittybopRole::OPERATOR)
            );

            $response = wp_remote_post($url, array(
                'headers' => $headers,
                'body' => wp_json_encode($data),
                'timeout' => 15
            ));

            if (is_wp_error($response)) {
                return SkittybopErrorCodes::SERVICE_UNAVAILABLE;
            }

            $status = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $response = json_decode($body);

            $isSuccess = $status == 200;
            if (!$isSuccess) {
                $error = $response->code;
                $noAvailableMinutes = $error === SkittybopErrorCodes::NO_AVAILABLE_MINUTES;
                return $error === $noAvailableMinutes ? $error : SkittybopErrorCodes::SERVICE_UNAVAILABLE;
            }

            return $response->jwt ?? SkittybopErrorCodes::SERVICE_UNAVAILABLE;
        }

        /**
         * Receive Heartbeat data and respond.
         *
         * Processes data received via a Heartbeat request, and returns additional data to pass back to the front end.
         *
         * @param array $response Heartbeat response data to pass back to front end.
         * @param array $data Data received from the front end (unslashed).
         *
         * @return array
         */
        public function skittybop_receive_heartbeat(array $response, array $data)
        {
            $outgoingCall = array_key_exists('skittybop_check_outgoing_call', $data) ? sanitize_text_field($data['skittybop_check_outgoing_call']) : null;
            $checkIncomingCall = array_key_exists('skittybop_check_incoming_call', $data) ? boolval(json_decode($data['skittybop_check_incoming_call'])) : false;
            if ($outgoingCall) {
                global $wpdb;
                $callsTable = $wpdb->prefix . SKITTYBOP_TABLE_CALLS;
                $user_id = get_current_user_id();

                //check for an accepted call with the provided room
                $accepted_call = $wpdb->get_results(
                    $wpdb->prepare("SELECT room FROM %i WHERE user_id = %d AND status = %d AND room = %s LIMIT 1",
                        array($callsTable, $user_id, SkittybopCallStatus::ACCEPTED, $outgoingCall)
                    )
                );
                if ($accepted_call) {
                    $resp = $this->requestToken($outgoingCall);
                    $isError = is_numeric($resp) && $resp < 0;
                    if ($isError) {
                        $response = array("room" => $outgoingCall, "jwt" => null, "error" => $resp);
                    }  else {
                        $response = array("room" => $outgoingCall, "jwt" => $resp, "error" => 0);
                    }
                    $response['skittybop_join_outgoing_call'] = wp_json_encode($response);
                }
            } else if ($checkIncomingCall && current_user_can(SkittybopRole::OPERATOR)) {
                global $wpdb;
                $callsTable = $wpdb->prefix . SKITTYBOP_TABLE_CALLS;
                $operatorId = get_current_user_id();

                //check for a new pending call for the current operator
                $pendingCall = $wpdb->get_results(
                    $wpdb->prepare("SELECT room FROM %i WHERE operator_id = %d AND status = %d ORDER BY created_at DESC LIMIT 1",
                        array($callsTable, $operatorId, SkittybopCallStatus::PENDING)
                    )
                );

                //let the operators accept or reject the incoming call
                if (!empty($pendingCall) && !empty($pendingCall[0]) && !empty($pendingCall[0]->room)) {
                    $response['skittybop_join_incoming_call'] = $pendingCall[0]->room;
                }
            }

            return $response;
        }


        public static function limit($start, $length)
        {
            if (isset($start) && $length != -1) {
                return " LIMIT " . $start . ", " . $length;
            }
            return "";
        }

        public static function order($order, $columns)
        {
            if (isset($order) && count($order)) {
                $orderBy = array();

                $columnIdx = $order['column'];
                $column = $columns[$columnIdx];
                $dir = $order['dir'] === 'asc' ? 'ASC' : 'DESC';
                $orderBy[] = '`' . $column . '` ' . $dir;

                if (count($orderBy)) {
                    return ' ORDER BY ' . implode(', ', $orderBy);
                }
            }
            return "";
        }

        public static function filter($search, $from, $to, $columns, &$bindings)
        {
            global $wpdb;
            $isAdministrator = current_user_can(SkittybopRole::ADMINISTRATOR);
            $isOperator = current_user_can(SkittybopRole::OPERATOR);
            $user_id = get_current_user_id();

            $globalSearch = array();
            $dateSearch = array();
            $dateBindings = array();

            if ($isAdministrator) {
                $where = "";
            } elseif ($isOperator) {
                $where = "c.operator_id = %d";
                $bindings[] = $user_id;
            } else {
                $where = "c.user_id = %d";
                $bindings[] = $user_id;
            }

            $iso8601_format = '%Y-%m-%dT%H:%i:%s.%fZ';
            if (!empty($from)) {
                $dateSearch[] = " c.started_at >= STR_TO_DATE(%s, %s) ";
                $dateBindings[] = $from;
                $dateBindings[] = $iso8601_format;
            }

            if (!empty($to)) {
                $dateSearch[] = " c.started_at < STR_TO_DATE(%s, %s) ";
                $dateBindings[] = $to;
                $dateBindings[] = $iso8601_format;
            }

            if (isset($search) && $search['value'] != '') {
                $str = $search['value'];
                for ($i = 0, $ien = count($columns); $i < $ien; $i++) {
                    $column = $columns[$i];
                    if ($column === 'id') {
                        if (is_numeric($str)) {
                            $bindings[] = intval($str);
                            $globalSearch[] = " c.id = %d";
                        }
                    } else if ($column === 'status') {
                        $mapping = array(
                            SkittybopCallStatus::PENDING => __("Pending", "skittybop"),
                            SkittybopCallStatus::ACCEPTED => __("Accepted", "skittybop"),
                            SkittybopCallStatus::CANCELED => __("Canceled", "skittybop"),
                            SkittybopCallStatus::FAILED => __("Failed", "skittybop"),
                            SkittybopCallStatus::REJECTED => __("Rejected", "skittybop")
                        );
                        $statusValue = -1;
                        foreach ($mapping as $status => $label) {
                            if (stripos($label, $str) !== false) {
                                $statusValue = $status;
                                break;
                            }
                        }
                        $bindings[] = intval($statusValue);
                        $globalSearch[] = " c.status = %d";
                    } else {
                        $bindings[] = '%' . $wpdb->esc_like($str) . '%';
                        $globalSearch[] = $column . " LIKE %s";
                    }
                }
            }

            if (count($globalSearch)) {
                $where = $where === '' ?
                    ' (' . implode(' OR ', $globalSearch) . ')' :
                    $where . ' AND ' . '(' . implode(' OR ', $globalSearch) . ') ';
            }

            if (count($dateSearch)) {
                $where = $where === '' ?
                    ' (' . implode(' AND ', $dateSearch) . ')' :
                    $where . ' AND ' . '(' . implode(' AND ', $dateSearch) . ') ';

                foreach ($dateBindings as $b) {
                    $bindings[] = $b;
                }
            }

            if ($where !== '') {
                $where = ' WHERE ' . $where;
            }

            return $where;
        }

    }
endif;
<?php

function neo4j_init ($host, $port, $user, $pass, $debug = null) {
    global $neo4jHandle;
    if (!$host) {
        throw new Exception("You must provide a host.");
    }
    if (!$port) {
        throw new Exception("You must provide a port.");
    }
    $nh = [host => $host, port => $port, user => $user, pass => $pass, debug => $debug];
    $neo4jHandle = $nh;
    return $nh;
}

function neo4j_prep ($query) {
    $i = 0;
    $params = func_get_args();
    $query = preg_replace_callback("/\?/", function () use (&$i, &$params) {
        $i++;
        $param = $params[$i];
        $param = neo4j_quote($param);
        return $param;
    }, $query, func_num_args() - 1);
    return $query;
}

function neo4j_quote ($param) {
    if (is_null($param)) {
        return "NULL";
    }
    elseif (is_int($param) || is_float($param)) {
        return $param;
    }
    elseif (is_bool($param)) {
        return $param ? "TRUE" : "FALSE";
    }
    elseif (is_array($param)) {
        $keys = array_keys($param);
        $is_assoc = $keys !== array_keys($keys);
        $str = "[";
        $i = 0;
        foreach ($param as $key => $val) {
            if ($i) {
                $str .= ", ";
            }
            $i++;
            if ($is_assoc) {
                $str .= neo4j_quote($key) . ", ";
            }
            $str .= neo4j_quote($val);
        }
        $str .= "]";
        return $str;
    }
    $param = preg_replace_callback("/[\\\\\"\b\f\n\r\t]/", function ($match) {
        if ($match[0] == "\\") {
            return "\\\\";
        }
        elseif ($match[0] == "\"") {
            return "\\\"";
        }
        elseif ($match[0] == "\b") {
            return "\\b";
        }
        elseif ($match[0] == "\f") {
            return "\\f";
        }
        elseif ($match[0] == "\n") {
            return "\\n";
        }
        elseif ($match[0] == "\r") {
            return "\\r";
        }
        elseif ($match[0] == "\t") {
            return "\\t";
        }
        else {
            return "";
        }
    }, $param);
    return "\"$param\"";
}

function neo4j_query ($query, $params = null) {
    global $neo4jHandle;
    return neo4j_exec($neo4jHandle, $query, $params);
}

function neo4j_exec ($nh, $query, $params = null) {
    $url = "http://$nh[host]/db/data/transaction/commit";
    $headers = [
        "X-Stream: true",
        "Content-Type: application/json",
        "Accept: application/json; charset=UTF-8",
    ];
    $statement = [];
    $statement["statement"] = $query;
    if ($params) {
        $statement["parameters"] = $params;
    }
    if ($nh["debug"]) {
        if ($params) {
            error_log(json_encode($statement, JSON_PRETTY_PRINT));
        }
        else {
            error_log($query);
        }
    }
    $statement["resultDataContents"] = ["rest"];
    $statements = ["statements" => [$statement]];
    $request = json_encode($statements, JSON_PRETTY_PRINT);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_PORT, $nh["port"]);
    curl_setopt($ch, CURLOPT_USERPWD, "$nh[user]:$nh[pass]");
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    if (!$response) {
        throw new Exception(curl_error($ch));
    }
    curl_close($ch);
    $response2 = neo4j_parse_response($response);
    if ($nh["debug"]) {
        error_log(json_encode($response2, JSON_PRETTY_PRINT));
    }
    return $response2;
}

function neo4j_parse_response ($response) {
    global $neo4jColumns, $neo4jData;
    $response2 = json_decode($response, 1);
    if (!$response2) {
        throw new Exception("Can't parse response.");
    }
    if ($response2["errors"]) {
        $message = $response2["errors"][0]["message"];
        $code = $response2["errors"][0]["code"];
        throw new Neo4jException($message, $code);
    }
    $response3 = $response2["results"][0];
    $neo4jColumns = $response3["columns"];
    $response4 = $response3["data"];
    $response5 = [];
    foreach ($response4 as $row) {
        $response5[] = $row["rest"];
    }
    neo4j_clean($response5);
    $neo4jData = $response5;
    return $response5;
}

function neo4j_clean (&$data) {
    if (!is_array($data))
        return;
    if (array_key_exists("metadata", $data)) {
        $data2 = $data["data"] ?: [];
        $meta = $data["metadata"];
        foreach ($data as $key => $value) {
            unset($data[$key]);
        }
        foreach ($data2 as $key => $value) {
            $data[$key] = $value;
        }
        foreach ($meta as $key => $value) {
            $data["_$key"] = $value;
        }
    }
    foreach ($data as &$item) {
        neo4j_clean($item);
    }
}

# normally the Exception object's code property needs to be a number. with Neo4j
# it needs to be a string, e.g. Neo.ClientError.General.ForbiddenOnReadOnlyDatabase
class Neo4jException extends Exception {
    public $code;
    public function __construct ($message, $code) {
        $this->code = $code;
        parent::__construct($message);
    }
}


<?php
require_once "neo4j.php";

list (, $host, $port, $user, $pass) = $argv;

neo4j_init($host, $port, $user, $pass, 1);
neo4j_query("CREATE (n:Neo4jPHP {yah: \"foo\"})");
neo4j_query("CREATE (n:Neo4jPHP {yah: {bar}})", ["bar" => "bar"]);
neo4j_query("MATCH (n:Neo4jPHP) RETURN n");
$query = neo4j_prep("MATCH (n:Neo4jPHP) WHERE n.yah=? RETURN n", "foo");
neo4j_query($query);
$query = neo4j_prep("MATCH (n:Neo4jPHP) SET n.thing=?", [1,2,3]);
neo4j_query($query);
$hash = neo4j_quote(["foo" => "bar", "baz" => "frob"]);
neo4j_query("MATCH (n:Neo4jPHP) SET n.thing=$hash");
neo4j_query("MATCH (n:Neo4jPHP) DELETE n RETURN n");
neo4j_query("incorrect syntax here");


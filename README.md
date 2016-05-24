neo4j.php
=========

neo4j.php is a client for Neo4j for PHP.

SYNOPSIS
========

    require_once "neo4j.php";
    $nh = neo4j_init("example.com", 7474, "user", "pass");
    $results = neo4j_exec($nh, "MATCH (n:Foo) RETURN n");
    foreach ($results as $row) {
        $node = $row[0];
        echo "$node[property]\n";
    }

INSTALLATION
============

Place neo4j.php into a folder. then in the script that wants to use it:

    require_once "path/to/neo4j.php";

INTERFACE
=========

* neo4j_init($host, $port, $user, $pass, $debug)

Sets the variables needed for connecting to a Neo4j server. Returns a handle which can be sent to neo4j_exec(). If you use the $debug argument, all requests and responses are printed to the error log (STDERR on command line).

* neo4j_exec($nh, $query, $params = null)

Executes a cypher query on the Neo4j handle provided by $nh. If you provide the params argument, they can be used in the query. Returns the results as a table. On error, it will raise an exception. The names of the columns of the resulting table are stored in a global variable $neo4jColumns. The response is also stored in global variable $neo4jData. The format of the response is an array of arrays. Each row is an array of its columns. the metadata for nodes is combined into the associative array prefixed with a underscore. For example, this query:

    neo4j_exec($nh, "MATCH (n:Foo) RETURN n, id(n)");

Might return results that are formatted like this:

    [
        [
            {
                "yah": "foo",
                "_id": 4577,
                "_labels": [
                    "Foo"
                ]
            },
            4577
        ],
        [
            {
                "yah": "foo",
                "_id": 4578,
                "_labels": [
                    "Foo"
                ]
            },
            4578
        ],
    ]

* neo4j_query($query, $params = null)

This is the same as neo4j_exec(), except it gets the Neo4j handle from the last call to neo4j_init().

* neo4j_quote($variable)

Quotes a variable for use in a cypher query. For example, "foo" becomes "\"foo\"". 23 becomes "23". null becomes "NULL".

* neo4j_prep($query, $var1, $var2, ..., $varn)

Replaces ? inside of query with the quoted version of $var1 ... $varn. For example:

    $query = neo4j_prep("MATCH (n:Foo) SET n.foo=?, n.bar=?", 55, "123 DELETE n");

Returns:

    "MATCH (n:Foo) SET n.foo=55, n.bar="123 DELETE n"



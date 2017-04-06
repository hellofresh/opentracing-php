<?php declare(strict_types=1);

namespace HelloFresh\BasicTracer;

class Tags
{
    //////////////////////////////////////////////////////////////////////
    // Component name
    //////////////////////////////////////////////////////////////////////

    /**
     * The software package, framework, library, or module that generated the associated Span.
     * E.g., "grpc", "django", "JDBI".
     */
    const COMPONENT = 'component';

    //////////////////////////////////////////////////////////////////////
    // DB Tags
    //////////////////////////////////////////////////////////////////////

    /**
     * DBInstance is database instance name.
     */
    const DB_INSTANCE = 'db.instance';

    /**
     * DBStatement is a database statement for the given database type.
     * It can be a query or a prepared statement (i.e., before substitution).
     */
    const DB_STATEMENT = 'db.statement';

    /**
     * DBType is a database type. For any SQL database, 'sql'.
     * For others, the lower-case database category, e.g. 'redis'
     */
    const DB_TYPE = 'db.type';

    /**
     * DBUser is a username for accessing database.
     */
    const DB_USER = 'db.user';

    //////////////////////////////////////////////////////////////////////
    // Error Tag
    //////////////////////////////////////////////////////////////////////

    /**
     * Error indicates that operation represented by the span resulted in an error.
     */
    const ERROR = 'error';

    //////////////////////////////////////////////////////////////////////
    // HTTP Tags
    //////////////////////////////////////////////////////////////////////

    /**
     * HTTPUrl should be the URL of the request being handled in this segment
     * of the trace, in standard URI format. The protocol is optional.
     */
    const HTTP_URL = 'http.url';

    /**
     * HTTPMethod is the HTTP method of the request, and is case-insensitive.
     */
    const HTTP_METHOD = 'http.method';

    /**
     * HTTPStatusCode is the numeric HTTP status code (200, 404, etc) of the HTTP response.
     */
    const HTTP_STATUS_CODE = 'http.status_code';

    //////////////////////////////////////////////////////////////////////
    // Message Bus Tag
    //////////////////////////////////////////////////////////////////////

    /**
     * MessageBusDestination is an address at which messages can be exchanged
     */
    const MESSAGE_BUS_DESTINATION = 'message_bus.destination';

    //////////////////////////////////////////////////////////////////////
    // Peer tags. These tags can be emitted by either client-side of
    // server-side to describe the other side/service in a peer-to-peer
    // communications, like an RPC call.
    //////////////////////////////////////////////////////////////////////

    /**
     * PeerService records the service name of the peer.
     */
    const PEER_SERVICE = 'peer.service';

    /**
     * PeerAddress records the address name of the peer. This may be a 'ip:port',
     * a bare 'hostname', a FQDN or even a database DSN substring
     * like 'mysql://username@127.0.0.1:3306/dbname'
     */
    const PEER_ADDRESS = 'peer.address';

    /**
     * PeerHostname records the host name of the peer
     */
    const PEER_HOSTNAME = 'peer.hostname';

    /**
     * PeerHostIPv4 records IP v4 host address of the peer
     */
    const PEER_HOST_IPV4 = 'peer.ipv4';

    /**
     * PeerHostIPv6 records IP v6 host address of the peer
     */
    const PEER_HOST_IPV6 = 'peer.ipv6';

    /**
     * PeerPort records port number of the peer
     */
    const PEER_PORT = 'peer.port';

    //////////////////////////////////////////////////////////////////////
    // Sampling hint
    //////////////////////////////////////////////////////////////////////

    /**
     * SamplingPriority determines the priority of sampling this Span.
     */
    const SAMPLING_PRIORITY = 'sampling.priority';

    //////////////////////////////////////////////////////////////////////
    // SpanKind (client/server or producer/consumer)
    //////////////////////////////////////////////////////////////////////

    /**
     * SpanKind hints at relationship between spans, e.g. client/server
     */
    const SPAN_KIND = 'span.kind';
}

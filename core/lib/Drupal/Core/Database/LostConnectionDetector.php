<?php
// phpcs:ignoreFile

namespace Drupal\Core\Database;

/**
 * This class contains a substantial portion of the same class in Laravel.
 *
 * cSpell:disable
 *
 * The MIT License (MIT)
 *
 * Copyright (c) Taylor Otwell
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */
class LostConnectionDetector {

  /**
   * If the error message contains any of these, it's a lost connection.
   *
   * @internal
   */
  public const array STRINGS = [
    'server has gone away',
    'Server has gone away',
    'no connection to the server',
    'Lost connection',
    'is dead or not enabled',
    'Error while sending',
    'decryption failed or bad record mac',
    'server closed the connection unexpectedly',
    'SSL connection has been closed unexpectedly',
    'Error writing data to the connection',
    'Resource deadlock avoided',
    'Transaction() on null',
    'child connection forced to terminate due to client_idle_limit',
    'query_wait_timeout',
    'reset by peer',
    'Physical connection is not usable',
    'TCP Provider: Error code 0x68',
    'ORA-03114',
    'Packets out of order. Expected',
    'Adaptive Server connection failed',
    'Communication link failure',
    'connection is no longer usable',
    'Login timeout expired',
    'SQLSTATE[HY000] [2002] Connection refused',
    'running with the --read-only option so it cannot execute this statement',
    'The connection is broken and recovery is not possible. The connection is marked by the client driver as unrecoverable. No attempt was made to restore the connection.',
    'SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo failed: Try again',
    'SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo failed: Name or service not known',
    'SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo for',
    'SQLSTATE[HY000]: General error: 7 SSL SYSCALL error: EOF detected',
    'SSL error: unexpected eof',
    'SQLSTATE[HY000] [2002] Connection timed out',
    'SSL: Connection timed out',
    'SQLSTATE[HY000]: General error: 1105 The last transaction was aborted due to Seamless Scaling. Please retry.',
    'Temporary failure in name resolution',
    'SQLSTATE[08S01]: Communication link failure',
    'SQLSTATE[08006] [7] could not connect to server: Connection refused Is the server running on host',
    'SQLSTATE[HY000]: General error: 7 SSL SYSCALL error: No route to host',
    'The client was disconnected by the server because of inactivity. See wait_timeout and interactive_timeout for configuring this behavior.',
    'SQLSTATE[08006] [7] could not translate host name',
    'TCP Provider: Error code 0x274C',
    'SQLSTATE[HY000] [2002] No such file or directory',
    'SSL: Operation timed out',
    'Reason: Server is in script upgrade mode. Only administrator can connect at this time.',
    'Unknown $curl_error_code: 77',
    'SSL: Handshake timed out',
    'SSL error: sslv3 alert unexpected message',
    'SSL error: ssl/tls alert unexpected message',
    'unrecognized SSL error code:',
    'SQLSTATE[HY000] [1045] Access denied for user',
    'SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it',
    'SQLSTATE[HY000] [2002] A connection attempt failed because the connected party did not properly respond after a period of time, or established connection failed because connected host has failed to respond',
    'SQLSTATE[HY000] [2002] Network is unreachable',
    'SQLSTATE[HY000] [2002] The requested address is not valid in its context',
    'SQLSTATE[HY000] [2002] A socket operation was attempted to an unreachable network',
    'SQLSTATE[HY000] [2002] Operation now in progress',
    'SQLSTATE[HY000] [2002] Operation in progress',
    'SQLSTATE[HY000]: General error: 3989',
    'went away',
    'No such file or directory',
    'server is shutting down',
    'failed to connect to',
    'Channel connection is closed',
    'Connection lost',
    'Broken pipe',
    'SQLSTATE[25006]: Read only sql transaction: 7',
    'failed: Connection refused',

    // PlanetScale MySQL / Vitess related things...
    'vtgate connection error: no healthy endpoints',
    'primary is not serving, there may be a reparent operation in progress',
    'current keyspace is being resharded',
    'no healthy tablet available',
    'transaction pool connection limit exceeded',
    'SSL operation failed with code 5',

    // PlanetScale PostgreSQL / pg_bouncer related things...
    'no primary available for branch',
    'no replica available for branch',
    'no running members available for branch',
    'failed to connect to upstream',
    'failed to send startup message',
    'failed to read startup message',
    'canceling statement due to conflict with recovery',
  ];

}

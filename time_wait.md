TCP TIME_WAIT reuse
===================

Overview
--------

Siege uses a new TCP connection for each request; HTTP keep-alive would not be a
realistic simulation of multiple concurrent users.

When the connection is closed, the socket switches to the TIME_WAIT state, which
stops the port from being re-used for a short period of time - this helps prevent
issues if there are delayed duplicate packets.

There are a limited amount of ports available - once you hit this limit, Siege will
be unable to make new requests, report errors, and the benchmark result is now
useless. Some of the targets in this suite execute extremely quickly (eg ~ 15,000
requests per second) which makes it likely that you will hit this limit.

How to fix it
-------------

In some situations, the kernel can infer that it is safe to re-use a socket that
is in TIME_WAIT. This can be enabled through /proc:

```sh
    echo 1 | sudo tee /proc/sys/net/ipv4/tcp_tw_reuse
```

Depending on your distribution, you may be able to make this persistent by editing
/etc/sysctl.conf, or adding a similarly-formatted file to /etc/sysctl.d/

If your workload involves many concurrent users, you may want to consider enabling
this on your production servers, regardless of which PHP engine you are using.

More details
------------

http://vincent.bernat.im/en/blog/2014-tcp-time-wait-state-linux.html describes the
states, and the situations in which tcp_tw_reuse has an effect.

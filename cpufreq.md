Unsuitable CPU speed policy
===========================

Overview
--------

Modern CPUs can run at several clock speeds, to choose a balance between
electricity usage/heat generation and performance. Modern kernels support
various ways of controlling this - the most common out-of-the-box one is
'ondemand'.

The ondemand policy automatically changes the CPU speed while the system is
running - unfortunately, there's lag involved, so this makes benchmark results
unreliable.

What we check
-------------

On Linux systems, there should be a directory for each CPU in
/sys/devices/system/cpu. If so, and CPU frequency scaling is enable,
/sys/devices/system/cpu/cpu<n>/cpufreq/scaling_governor should exist, and
contain the name of the current policy.

If that file does not exist, we assume everything is good. If it does, we check
that contains 'performance', for every CPU.

How to fix it
-------------

The generic solution is to run this as root:

```sh
    for file in /sys/devices/system/cpu/cpu*/cpufreq/scaling_governor; do
      echo performance > $file
    done
```

Your distribution or desktop environment may provide power management features
that interfere with this. You will need to check their documentation as
appropriate. Also, if you are running a desktop environment, this may indicate
that your system is more generally not representative of a production server.

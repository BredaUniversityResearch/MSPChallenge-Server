PHP_ARG_ENABLE([msp_tracker],
  [whether to enable the MSP tracker extension],
  [AS_HELP_STRING([--enable-msp_tracker], [Enable MSP tracker extension])],
  [yes])

if test "$PHP_MSP_TRACKER" != "no"; then
  PHP_NEW_EXTENSION([msp_tracker], [msp_tracker.c], [$ext_shared])
fi


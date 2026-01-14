#!/usr/bin/awk -f

{
    orig = $0
    new = orig

    gsub(/\?/, "_", new)
    gsub(/&/, "_", new)
    gsub(/%/, "_", new)
    gsub(/:/, "_", new)
    gsub(/;/, "_", new)

    if (orig != new) {
        print "mv \"" orig "\" \"" new "\""
    }
}

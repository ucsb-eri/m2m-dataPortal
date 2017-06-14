#!/usr/bin/env bash
datadir=$1; shift;

[ -d $datadir ] || mkdir $datadir
cd $datadir
printf "== README contents for $datadir\n_writeup_ to test this meta data\n" > README.md
for d in aDir bDir cDir dDir; do
    [ -d $d ] || mkdir $d
    printf "== README \n_Contents_ for $datadir/$d\n" > $d/README.md
    for sd in aSubDir bSubDir cSubDir; do
        [ -d $d/$sd ] || mkdir $d/$sd
        for f in aa bb cc dd ee ff gg; do
            printf "file - $datadir/$d/$sd/$f\n" > $d/$sd/$f
        done
    done
    for df in aFile bFile cFile; do
        printf "file - $datadir/$d/$df\n" > $d/$df
    done
done
exit 0

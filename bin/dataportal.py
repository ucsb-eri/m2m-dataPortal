#!/usr/bin/env python
#https://wiki-stadm.eri.ucsb.edu/Dataportal

# USER CONFIGURATION PARAMETERS:
# dataroot: absolute path to root directory containing data dirs
# dataroot should have a trailing '/'
dataroot = "/home/biogeog/projects/macrosystem/m2m-dataPortal/webroot/data/"


# do not touch anything below this line
# ===================================================================================

import sys
# just bail if the python version is too old (ie: 2.4.x circa CentOS-5.x)
# this to quiet errors from older CentOS-5.x systems
if sys.version_info[0] == 2 and sys.version_info[1] < 6 :
    #if opt.debug:
    #    print "python version is not high enough, bailing"
    sys.exit(0)

# not sure if needed
import glob

# def need these
import os
import fnmatch
import json
import sqlite3
from stat import *

# TODO: wipe old db and create new db every time script is called? Y for now

# make sqlite db if table does not exist
# table names: imports, datafiles
imports_schema       = 'id TEXT PRIMARY KEY, path TEXT, type TEXT, epoch INTEGER'
datafiles_schema     = 'id TEXT PRIMARY KEY, filename TEXT, epoch INTEGER, filesize INTEGER,'
# datafiles_schema 	+= 'data1 TEXT'		# other metadata, if needed
datafiles_schema     += 'importId TEXT, FOREIGN KEY(importID) REFERENCES imports(id)'

# Starting to think more along the lines of directory - file tables instead of import - file tables.
# The import idea is potentially useful, but I think we want to start with a simpler model.
# not sure if we will use the epoch entries at this stage... might be more useful to still have an import table
# that logs in when the import is done.  But at this point
i="CREATE TABLE IF NOT EXISTS import (iid INTEGER PRIMARY KEY, topdir TEXT UNIQUE ON CONFLICT REPLACE, epoch INTEGER DEFAULT (CAST(STRFTIME('%s','now') AS INTEGER)))"
d="CREATE TABLE IF NOT EXISTS dirs (did INTEGER PRIMARY KEY, dname TEXT, dpath TEXT, dctime INTEGER, dmtime INTEGER)"
f="CREATE TABLE IF NOT EXISTS files (fid INTEGER PRIMARY KEY, f_did INTEGER, fname TEXT, fpath TEXT, fctime INTEGER, fmtime INTEGER, bytes INTEGER)"
# track directories using a dict, keep track of an id there
# as a new directory shows up, assign new id, insert into db
# as files in that dir are processed, use that dir id as a foreign key
# Also want to have a desc table that will get readmes inserted into

# on the listing end
# Display readme for the given directory
# list directories
# list downloads (files)

# if table does not exist
# open up sqlite db
#


class m2mPortalCrawl:
    def __init__(self,top):
        self.dirID = 1
        # actually, not even sure we need this.
        # Each directory is processed one at a time, so we can just process sequentially
        self.dirDict = {}
        print "Top: " + top
        self.crawl(top)

    # Here is an example of os.walk
    # Would need to be heavily adapted for use here, but this demonstrates some of its utility
    # The whole crawl should probably be wrapped in a BEG/END TRANSACTION
    def crawl(self,topdir):
        dlist = []
        ltd = len(topdir)
        # root is the current directory being examined
        # dirs is a list of the directories found at root
        # files is a list of the files found at root
        # we want to know what topdir is, but other than that, we want paths relative
        # to that for building links in webroot.  So lets deal with that here.
        for root, dirs, files in os.walk(topdir):
            dirID = self.dirID   # copy object dirID to a local var
            self.dirID += 1

            # derive relative current directory.
            # this should work OK, but we could verify root[0:ltd] == topdir
            rel = root[ltd:]

            # map directory (relative to top) to a directory ID, not sure that actually needed
            #self.dirDict[rel] = self.dirID

            dd = os.path.basename(root)
            # gather info and insert into db
            self.dirEntry(dd,root,topdir,rel,dirID)
            print "root: "+root
            print "rel: "+rel
            print "dirs: ",dirs
            dlist = [(os.path.join(rel,d)) for d in dirs]
            print "dlist: ",dlist

            for ff in files:
                print "file: " + ff
                self.fileEntry(ff,root,topdir,rel,dirID)


    def dirEntry(self,dirname,root,top,rel,id):
        # gather info and insert into db
        st = os.stat(root)
        #st.st_mtime
        #st.st_ctime

    def fileEntry(self,filename,root,top,rel,did):
        # gather file into and insert into db
        st = os.stat(os.path.join(root,filename))
        #st.st_mtime
        #st.st_ctime
        #st.st_size


m2m = m2mPortalCrawl(dataroot)

#
# # get the data files
# # os recursive walk maybe? if not then we can try glob
# pattern = "bio_*.aux"
# files = os.listdir(dataroot)
# for name in files:
# 	# write data files to sqlite db
# 	pass

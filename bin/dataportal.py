#!/usr/bin/env python
#https://wiki-stadm.eri.ucsb.edu/Dataportal

# USER CONFIGURATION PARAMETERS:
# dataroot: absolute path to root directory containing data dirs
# dataroot should have a trailing '/'
dataroot = "../webroot/data/"
dbfile = "../var/m2m-dataportal.sqlite3"

# do not touch anything below this line
# ===================================================================================

import sys
import os
import json
import sqlite3
from stat import *

# just bail if the python version is too old (ie: 2.4.x circa CentOS-5.x)
# this to quiet errors from older CentOS-5.x systems
if sys.version_info[0] == 2 and sys.version_info[1] < 6 :
    #if opt.debug:
    #    print "python version is not high enough, bailing"
    sys.exit(0)

# not sure if needed
#import glob
#import fnmatch

# TODO: wipe old db and create new db every time script is called? Y for now

# make sqlite db if table does not exist
# table names: imports, datafiles
#imports_schema       = 'id TEXT PRIMARY KEY, path TEXT, type TEXT, epoch INTEGER'
#datafiles_schema     = 'id TEXT PRIMARY KEY, filename TEXT, epoch INTEGER, filesize INTEGER,'
## datafiles_schema 	+= 'data1 TEXT'		# other metadata, if needed
#datafiles_schema     += 'importId TEXT, FOREIGN KEY(importID) REFERENCES imports(id)'

# Starting to think more along the lines of directory - file tables instead of import - file tables.
# The import idea is potentially useful, but I think we want to start with a simpler model.
# not sure if we will use the epoch entries at this stage... might be more useful to still have an import table
# that logs in when the import is done.  But at this point
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
    def __init__(self,top,dbfile):
        self.dirID = 0
        self.parentDir = {}
        self.top = top
        self.conn = sqlite3.connect(dbfile)
        self.initTables()
        #self.conn.execute()
        # actually, not even sure we need this.
        # Each directory is processed one at a time, so we can just process sequentially
        self.dirDict = {}
        print "Top: " + top
        #self.conn.execute("BEGIN TRANSACTION;")
        self.crawl(top)
        self.updateParentDirs()
        #self.conn.execute("END TRANSACTION;")
        self.conn.commit()
        self.conn.close()

    def initTables(self):
        print "Initializing DB"

        idq="DROP TABLE IF EXISTS import;"
        ddq="DROP TABLE IF EXISTS dirs;"
        fdq="DROP TABLE IF EXISTS files;"

        icq="CREATE TABLE IF NOT EXISTS import (iid INTEGER PRIMARY KEY, top TEXT UNIQUE ON CONFLICT REPLACE, epoch INTEGER DEFAULT (CAST(STRFTIME('%s','now') AS INTEGER)));"
        dcq="CREATE TABLE IF NOT EXISTS dirs (did INTEGER PRIMARY KEY, dparent INTEGER, dname TEXT, dpath TEXT, dctime INTEGER, dmtime INTEGER);"
        fcq="CREATE TABLE IF NOT EXISTS files (fid INTEGER PRIMARY KEY, f_did INTEGER, fname TEXT, fpath TEXT, fctime INTEGER, fmtime INTEGER, bytes INTEGER);"

        self.conn.execute(idq)
        self.conn.execute(ddq)
        self.conn.execute(fdq)

        self.conn.execute(icq)
        self.conn.execute(dcq)
        self.conn.execute(fcq)

        self.conn.execute("INSERT INTO import (top) VALUES (?)",[self.top])

    def updateParentDirs(self):
        for dirKey in self.parentDir:
            for relpath in self.parentDir[dirKey]:
                self.conn.execute("UPDATE dirs SET dparent=? WHERE dpath = ?",[dirKey,relpath])


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
        #self.dirDict[''] = 0;
        for root, dirs, files in os.walk(topdir):
            dirID = self.dirID   # copy object dirID to a local var
            self.dirID += 1
            self.parID = dirID

            # derive relative current directory.
            # this should work OK, but we could verify root[0:ltd] == topdir
            rel = root[ltd:]

            # map directory (relative to top) to a directory ID, not sure that actually needed
            self.dirDict[rel] = dirID

            dd = os.path.basename(root)
            # gather info and insert into db
            self.dirEntry(dd,root,topdir,rel,dirID)
            print "root: "+root
            print "rel: "+rel
            print "dirs: ",dirs
            dlist = [(os.path.join(rel,d)) for d in dirs]
            print "dlist: ",dlist
            self.parentDir[dirID] = dlist

            ## generate the dict entries for upcoming dirs
            #for sd in dlist:
            #    pass


            # Need to be able to derive a list of dirs from their parent
            # 0 is our topdir, so we want all its children to have dparent set to 0

            for ff in files:
                print "file: " + ff
                self.fileEntry(ff,root,topdir,rel,dirID)


    def dirEntry(self,dirname,root,top,rel,id):
        # gather info and insert into db
        st = os.stat(root)
        self.conn.execute("INSERT into dirs (did,dname,dpath,dctime,dmtime) VALUES (?,?,?,?,?)",[id,dirname,rel,st.st_ctime,st.st_mtime])
        #st.st_mtime
        #st.st_ctime

    def fileEntry(self,filename,root,top,rel,did):
        # gather file into and insert into db
        st = os.stat(os.path.join(root,filename))
        if rel:
            path=rel+'/'+filename
        else:
            path=filename
        self.conn.execute("INSERT into files (f_did,fname,fpath,fctime,fmtime,bytes) VALUES (?,?,?,?,?,?)",[did,filename,path,st.st_ctime,st.st_mtime,st.st_size])

################################################################################
## Main
################################################################################
m2m = m2mPortalCrawl(dataroot,dbfile)

#
# # get the data files
# # os recursive walk maybe? if not then we can try glob
# pattern = "bio_*.aux"
# files = os.listdir(dataroot)
# for name in files:
# 	# write data files to sqlite db
# 	pass

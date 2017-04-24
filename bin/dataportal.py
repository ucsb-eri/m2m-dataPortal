#!/usr/bin/env python
#https://wiki.eri.ucsb.edu/stadm/Dataportal

# USER CONFIGURATION PARAMETERS:
# dataroot: absolute path to root directory containing data dirs
dataroot = "/home/biogeog/projects/macrosystem/m2m-dataPortal/stubby/mcstubberson"


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


# TODO: wipe old db and create new db every time script is called?

# make sqlite db if table does not exist
# table names: imports, datafiles
imports_schema 		 = 'id TEXT PRIMARY KEY, path TEXT, type TEXT, epoch INTEGER'
datafiles_schema 	 = 'id TEXT PRIMARY KEY, filename TEXT, epoch INTEGER, filesize INTEGER,'
# datafiles_schema 	+= 'data1 TEXT'		# other metadata, if needed
datafiles_schema	+= 'importId TEXT, FOREIGN KEY(importID) REFERENCES imports(id)'

# if table does not exist






# open up sqlite db



# # get the data files
# # os recursive walk maybe? if not then we can try glob
# pattern = "bio_*.aux"
# files = os.listdir(dataroot)
# for name in files:
# 	# write data files to sqlite db
# 	pass
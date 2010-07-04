# Makefile for phpxmlrpc extras
# $Id: Makefile,v 1.13 2008/03/07 16:40:06 ggiunta Exp $

### USER EDITABLE VARS ###

# path to PHP executable, preferably CLI version
PHP=/usr/local/bin/php

# path were xmlrpc lib files will be copied to
PHPINCLUDEDIR=/usr/local/lib/php

# mkdir is a thorny beast under windows: make sure we can not use the cmd version, running eg. "make MKDIR=mkdir.exe"
MKDIR=mkdir

#find too
FIND=find


#### DO NOT TOUCH FROM HERE ONWARDS ###

export VERSION=0.5
# better alternative: try to recover version number from code
#export VERSION=$(shell egrep "\$GLOBALS *\[ *'xmlrpcVersion' *\] *= *'" xmlrpc.inc | sed -r s/"(.*= *' *)([0-9a-zA-Z.-]+)(.*)"/\\2/g )

MAINFILES=ChangeLog Makefile NEWS README docxmlrpcs.*

EPIFILES=xmlrpc_extension_api/xmlrpc_extension_api.inc \
 xmlrpc_extension_api/testsuite.php

AJAXFILES=ajax/ajaxmlrpc.inc \
 ajax/ajaxdemo.php \
 ajax/ajaxdemo2.php \
 ajax/sonofajax.php

JSONFILES=jsonrpc/jsonrpc.inc \
 jsonrpc/benchmark.php \
 jsonrpc/jsonrpcs.inc \
 jsonrpc/server.php \
 jsonrpc/json_extension_api.inc \
 jsonrpc/testsuite.php

PROXYFILES=proxy/proxyxmlrpcs.inc \
 proxy/proxyserver.php

WSDLFILES=wsdl/schema.rnc \
 wsdl/schema.rng \
 wsdl/xmlrpc.wsdl \
 wsdl/xmlrpc.xsd

ADODBFILES=adodb/*.php adodb/*.txt adodb/*.svg adodb/lib/*.php adodb/server/*.php adodb/drivers/*.php

all: install

install:
	@echo Please install by hand the needed components, copying the files into the appropriate directory
	cd doc && $(MAKE) install


### the following targets are to be used for library development ###

dist: xmlrpc-extras-${VERSION}.zip xmlrpc-extras-${VERSION}.tar.gz

xmlrpc-extras-${VERSION}.zip xmlrpc-extras-${VERSION}.tar.gz: ${MAINFILES} ${EPIFILES} ${AJAXFILES} ${PROXYFILES} ${WSDLFILES} ${ADODBFILES}
	@echo ---${VERSION}---
	rm -rf extras-${VERSION}
	${MKDIR} extras-${VERSION}
	${MKDIR} extras-${VERSION}/ajax
	${MKDIR} extras-${VERSION}/jsonrpc
	${MKDIR} extras-${VERSION}/proxy
	${MKDIR} extras-${VERSION}/wsdl
	${MKDIR} extras-${VERSION}/adodb
	${MKDIR} extras-${VERSION}/adodb/drivers
	${MKDIR} extras-${VERSION}/adodb/lib
	${MKDIR} extras-${VERSION}/adodb/server
	${MKDIR} extras-${VERSION}/xmlrpc_extension_api
	cp --parents ${AJAXFILES} extras-${VERSION}
	cp --parents ${JSONFILES} extras-${VERSION}
	cp --parents ${PROXYFILES} extras-${VERSION}
	cp --parents ${WSDLFILES} extras-${VERSION}
	cp --parents ${ADODBFILES} extras-${VERSION}
	cp --parents ${EPIFILES} extras-${VERSION}
	cp ${MAINFILES} extras-${VERSION}
	cd doc && $(MAKE) dist
#   on unix shells last char should be \;
	${FIND} extras-${VERSION} -type f ! -name "*.fttb" ! -name "*.pdf" ! -name "*.gif" -exec dos2unix {} ;
	-rm xmlrpc-extras-${VERSION}.zip xmlrpc-extras-${VERSION}.tar.gz
	tar -cvf xmlrpc-extras-${VERSION}.tar extras-${VERSION}
	gzip xmlrpc-extras-${VERSION}.tar
	zip -r xmlrpc-extras-${VERSION}.zip extras-${VERSION}
doc:
	cd doc && $(MAKE) doc

clean:
	rm -rf extras-${VERSION} xmlrpc-extras-${VERSION}.zip xmlrpc-extras-${VERSION}.tar.gz
	cd doc && $(MAKE) clean

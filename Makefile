tout: example.html

clean:
	rm -f *.html *.dot *.pdf *.svg

.SECONDARY: # Sans ça GNU Make rm toutes les cibles intermédiaires…

%.html: %.wsdl %.pdf %.svg
	php xsif.php $< --texte
	./henri $@ || rm -f $@
	echo fini

%.dot: %.wsdl
	php xsif.php $<

%.pdf: %.dot
	dot $< -Tpdf -o $@

%.svg: %.dot
	dot $< -Tsvg -o $@

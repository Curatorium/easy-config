all: $(patsubst %.in.yml,%.test.yml,$(wildcard *.in.yml))

%.test.yml: %.in.yml %.out.yml
	../bin/ez-cfg --out-files $@ prod-2 $<;
	diff -ur $*.out.yml $@;

clean:
	rm -f *.test.yml

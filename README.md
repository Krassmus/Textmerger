# TextMerger
A 3-way-textmerger for PHP and JavaScript

## What is a 3-way-text-merger?

Typically you have a text, you work with it, change it. And when you want to save it back to its central storage, you realise that this text has also been altered by another person. What to do now? In this case you create diffs for both changed versions of the same text and compare them. If the diffs have conflicts you need to take a look on them. Most of the times you are able to solve the conflicts on your own.

When it comes to text-editing tools online we have this problem quite often and want a library to solve that problem on the fly without the need of a human watcher to get in the process.

## What does Textmerger do?

All 3-way-diffing tools or 3-way-textmerger I found in the internet so far are not capable to solve conflicts. So I needed to build one for myself. It works this way:

1. It looks up if one version is exactly the original version. If that's the case the other version will be the result of the merging.
2. It creates a general simple diff for both textversions. This simple diff is just the information "changed from character x to character y". If both simple diffs have no conflict, we can alter the original text by both diffs one at a time.
3. If we have a conflict in these simple diffs, it tries to break both diffs down into smaller pieces in the hope that they are solvable. For example if a diff is to add "&lt;i&gt;" at the beginning of a sentence and "&lt;/i&gt;" at the end of a sentence, the algorith realises that the sentence itself has not been changed at all. This happens with the [levenshtein-algorithm (with backtrace)](https://en.wikipedia.org/wiki/Levenshtein_distance), which usually helps to measure the character-difference between two strings, but can also be used to create a change-path frome one string to another.
4. For performance-reason this levenshtein-algorithm will be done multiple times: at first paragraph-wise, secondly word-wise and thirdly character-wise. That's because character-wise is the most exact way but also the slowest and memory-lavish.

This will solve most conflicts very good (better than everything I've seen so far) and I guess it's even optimal. But still conflicts might happen, when two people really changed the same character. You can configure Textmerger as how it should handle these conflicts. It can automatically:

1. Use the larger change of characters. Like person A edited 7 characters and person B edited 3 characters, which are not solvable. In this case person A wins and the changes of person B would be rejected by the algorithm.
2. In a conflict all changes by person A should be preferred.
3. In a conflict all changes by person B should be preferred.
4. Throw an exception when a true conflict happens.

## License

MIT license for all purposes

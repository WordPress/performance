module.exports = function(grunt) {
 
    grunt.initConfig({
        jshint: {
			options: grunt.file.readJSON( '.jshintrc' ),
			grunt: {
				src: ['Gruntfile.js']
			},
            files: [
                'modules/images/webp-uploads/fallback.js'
            ]
        }
    });
 
    grunt.loadNpmTasks('grunt-contrib-jshint');
 
    grunt.registerTask('default', [
        'jshint'
    ]);
 
};
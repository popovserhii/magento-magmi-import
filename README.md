# Magmi Import

Module supply convenient wrapper for Magmi with support jobs (hooks).

## Image import
Image import has very flexible config which allow import any complex media structure.
You should create your import config file in *app/etc/*, for example *app/etc/import.xml* (Magento automatically
import this file). And provide config with your requirement.

Config explanation
```
<config>
	<popov_magmi>
        <general_import>
            <!-- path to magmi console script -->
            <magmi_cli_pathname>../vendor/dweeves/magmi/magmi/cli/magmi.cli.php</magmi_cli_pathname>
        </general_import>
		<image_import>
			<simple_import><!-- import name -->
				<!-- Path to images source. Must be equal with magmi images source option -->
				<source_path>media/import/images</source_path>
	
				<!--
                If type is "dir" then images should be put in this directory
                if type is "file" then images should be sought in current directory
                -->
				<type>file</type>
	
				<!--
				Important only for <type /> is "dir" and sub direcoties contain images for simple product then must be "configurable".
				Otherwise simply use "simple"
				-->
				<product_type>simple</product_type><!-- simple | configurable -->
	
				<!-- 
				If filename contain only unique attribute value (sku) without any additional simbols than you should
				only to do bind to your attribute. Otherwise you must to use RegEx Named Groups Capturing (https://stackoverflow.com/a/6971356/1335142)
				in patterns, for example **<pattern>(?P<sku>.*)_[\d]+.jpg</pattern>** which will be parse next filename
				*00SKZP&Slash&0NTGA_1.jpg*
				-->
				<name>
					<!--<pattern></pattern>-->
					<to_attribute>sku</to_attribute><!-- filename to attribute name -->
				</name>
	
				<!--
				Image import configuration is based on **glob** function. 
				You can use any glob pattern for get needed images.
				If you fill only *media_gallery* than first image will be getted for 'image', 'small_image' and 'thumbnail'.
				Path is relative to parent path.
				You can also use any native or custom template directives (see paragraph "Enhancements") in "images" config for more flexibility.
				-->
				<images>
					<image>{{var product.sku}}.jpg</image>
					<!--<small_image />-->
					<!--<thumbnail />-->
					<!--<media_gallery />-->
				</images>
	
				<!-- You can overwirte any default values for specific configuration -->
				<options>
					<values>
						<visibility>1</visibility> <!-- set not visible for products -->
					</values>
					<backup_images>1</backup_images>
				</options>
	
				<!-- Script can handle any nested structure but in real life more than two level is not used -->
				<scan>
					<!-- Put here any number nested config described above -->
				</scan>
			</simple_import>
		</image_import>
	</popov_magmi>
</config>
```

## Jobs
Any number of additional jobs can be executed.
Simply and declaration in your config. 
```
</config>
    <popov_magmi>
		<product_import />

		<jobs>
			<product_import>
				<!-- This tag name doesn't matter. It only must be -->
				<file_not_found>
					<alter>pre</alter>
					<run>
						<helper>popov_magmi/job_fileNotFound::run</helper>
					</run>
				</file_not_found>
			</product_import>
		</jobs>
	</popov_magmi>
</config>
```

## Notification
If during import file does not find you can setup email notification in admin panel 

## Enhancements
Module has custom template directive *{{specChar}}* with allow decode and encode value for correct path resolving.
If your sku contains special symbols which don't allowed operation system than you can resolve this with replacement.
For example, sku "00SKVS/0DANX" become "00SKVS&Slash&0DANX" and you can use this in file name as "00SKVS&Slash&0DANX.jpg"

### Usage
In your glob pattern you can use needed replacement
```
{{specChar encode=$product.sku}}
// or
{{specChar decode=$product.sku}}
```

